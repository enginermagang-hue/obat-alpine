<?php

namespace App\Jobs;

use App\Models\Obat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportObatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = DB::table('imports')->where('id', $this->importId)->first();

        if (! $import) {
            return;
        }

        $path = Storage::disk('local')->path($import->file_path);

        if (! is_file($path)) {
            DB::table('imports')->where('id', $this->importId)->update([
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $failed = [];
        $processed = 0;
        $success = 0;

        foreach ($csv->getRecords() as $line => $row) {
            $processed++;

            $data = $this->normalize($row);

            if ($data === null) {
                $failed[] = array_merge(['_baris' => $line, '_kesalahan' => 'kode_obat dan nama_obat wajib diisi'], $row);

                continue;
            }

            try {
                Obat::updateOrCreate(
                    ['kode_obat' => $data['kode_obat']],
                    $data,
                );
                $success++;
            } catch (\Throwable $e) {
                $failed[] = array_merge(['_baris' => $line, '_kesalahan' => $e->getMessage()], $row);
            }

            if ($processed % 100 === 0) {
                DB::table('imports')->where('id', $this->importId)->update([
                    'processed_rows' => $processed,
                    'successful_rows' => $success,
                    'updated_at' => now(),
                ]);
            }
        }

        $failedPath = null;

        if ($failed !== []) {
            $failedPath = 'imports/'.$this->importId.'-gagal.csv';
            $handle = fopen(Storage::disk('local')->path($failedPath), 'w');
            fputcsv($handle, array_keys($failed[0]));
            foreach ($failed as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }

        DB::table('imports')->where('id', $this->importId)->update([
            'processed_rows' => $processed,
            'successful_rows' => $success,
            'total_rows' => $processed,
            'completed_at' => now(),
            'updated_at' => now(),
            'file_path' => $failedPath ?? $import->file_path,
        ]);
    }

    /**
     * Normalisasi satu baris CSV. Return null bila baris tidak valid.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalize(array $row): ?array
    {
        $get = fn (string $key): ?string => isset($row[$key]) && trim((string) $row[$key]) !== ''
            ? trim((string) $row[$key])
            : null;

        $kode = $get('kode_obat');
        $nama = $get('nama_obat');

        if ($kode === null || $nama === null) {
            return null;
        }

        $bentuk = strtolower((string) ($get('bentuk_sediaan') ?? 'tablet'));
        $bentukValid = ['tablet', 'kapsul', 'sirup', 'salep', 'injeksi', 'drop', 'inhaler', 'suppositoria'];

        $metode = strtolower((string) ($get('metode_stok') ?? 'fefo'));

        $ven = strtoupper((string) ($get('ven_kategori') ?? ''));

        return [
            'kode_obat' => $kode,
            'nama_obat' => $nama,
            'nama_generik' => $get('nama_generik'),
            'kategori' => $get('kategori') ?? 'Lainnya',
            'satuan' => $get('satuan') ?? 'Tablet',
            'kekuatan' => $get('kekuatan'),
            'bentuk_sediaan' => in_array($bentuk, $bentukValid, true) ? $bentuk : 'tablet',
            'produsen' => $get('produsen'),
            'kemasan' => $get('kemasan'),
            'harga_satuan' => is_numeric($get('harga_satuan')) ? (float) $get('harga_satuan') : null,
            'status' => in_array(strtolower((string) ($get('status') ?? 'aktif')), ['aktif', 'nonaktif'], true)
                ? strtolower((string) $get('status'))
                : 'aktif',
            'ven_kategori' => in_array($ven, ['V', 'E', 'N'], true) ? $ven : null,
            'metode_stok' => in_array($metode, ['fefo', 'fifo', 'lifo'], true) ? $metode : 'fefo',
        ];
    }
}
