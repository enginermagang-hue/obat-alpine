<?php

namespace App\Console\Commands;

use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\ModelPrediksi;
use App\Models\Obat;
use App\Models\PrediksiKebutuhan;
use App\Models\StokFaskes;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DocsPrediksiJsonCommand extends Command
{
    protected $signature = 'docs:prediksi-json
        {--fasilitas-id=6 : ID fasilitas kesehatan sumber data}
        {--obat-id=44,19,11 : Daftar ID obat dipisah koma (ID pertama = obat sampel utama)}
        {--mulai=2020-01 : Bulan awal periode (format YYYY-MM)}
        {--sampai=2024-12 : Bulan akhir periode (format YYYY-MM)}
        {--output=tmp/prediksi-data.json : Path output relatif dari project root}';

    protected $description = 'Export data pemakaian aktual ke JSON untuk skrip dokumentasi prediksi AI';

    public function handle(): int
    {
        $faskes = FasilitasKesehatan::find((int) $this->option('fasilitas-id'));

        if ($faskes === null) {
            $this->error("Fasilitas ID {$this->option('fasilitas-id')} tidak ditemukan.");

            return self::FAILURE;
        }

        $obatIds = collect(explode(',', (string) $this->option('obat-id')))
            ->map(fn (string $v): int => (int) trim($v))
            ->filter()
            ->unique()
            ->values();

        if ($obatIds->isEmpty()) {
            $this->error('Opsi --obat-id kosong.');

            return self::FAILURE;
        }

        try {
            $mulai = Carbon::createFromFormat('Y-m', (string) $this->option('mulai'))->startOfMonth();
            $sampai = Carbon::createFromFormat('Y-m', (string) $this->option('sampai'))->startOfMonth();
        } catch (\Throwable) {
            $this->error('Format --mulai/--sampai harus YYYY-MM.');

            return self::FAILURE;
        }

        if ($mulai->gt($sampai)) {
            $this->error('--mulai tidak boleh setelah --sampai.');

            return self::FAILURE;
        }

        $bulanList = [];
        $cursor = $mulai->copy();
        while ($cursor->lte($sampai)) {
            $bulanList[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $expr = $isSqlite
            ? "strftime('%Y-%m', p.tanggal_pemakaian)"
            : "DATE_FORMAT(p.tanggal_pemakaian, '%Y-%m')";

        $obatRows = [];
        $pemakaian = [];
        $stokSekarang = [];
        $modelInfo = [];
        $prediksiTerakhir = [];

        foreach ($obatIds as $obatId) {
            $obat = Obat::find($obatId);

            if ($obat === null) {
                $this->warn("Obat ID {$obatId} tidak ditemukan, dilewati.");

                continue;
            }

            $records = DB::table('detail_pemakaian_obat as d')
                ->join('pemakaian_obat as p', 'p.id', '=', 'd.pemakaian_id')
                ->selectRaw("{$expr} as bulan, SUM(d.jumlah) as total")
                ->where('p.fasilitas_id', $faskes->id)
                ->where('d.obat_id', $obat->id)
                ->whereDate('p.tanggal_pemakaian', '>=', $mulai->toDateString())
                ->whereDate('p.tanggal_pemakaian', '<=', $sampai->copy()->endOfMonth()->toDateString())
                ->groupBy('bulan')
                ->orderBy('bulan')
                ->pluck('total', 'bulan')
                ->map(fn ($v): int => (int) $v)
                ->toArray();

            $seri = [];
            foreach ($bulanList as $bulan) {
                $seri[$bulan] = $records[$bulan] ?? 0;
            }

            $nonZero = count(array_filter($seri, fn (int $v): bool => $v > 0));
            if ($nonZero < 6) {
                $this->warn("{$obat->nama_obat}: hanya {$nonZero} bulan berisi (< 6, di bawah syarat ANN).");
            }

            $stok = StokFaskes::query()
                ->where('fasilitas_id', $faskes->id)
                ->where('obat_id', $obat->id)
                ->value('jumlah');

            if ($stok === null) {
                $stok = (int) BatchStok::query()
                    ->where('fasilitas_id', $faskes->id)
                    ->where('obat_id', $obat->id)
                    ->where('status', 'tersedia')
                    ->sum('jumlah');
            }

            $model = ModelPrediksi::query()
                ->where('fasilitas_id', $faskes->id)
                ->where('obat_id', $obat->id)
                ->orderByDesc('id')
                ->first();

            $prediksi = PrediksiKebutuhan::query()
                ->where('fasilitas_id', $faskes->id)
                ->where('obat_id', $obat->id)
                ->orderByDesc('periode_tahun')
                ->orderByDesc('periode_bulan')
                ->limit(3)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (PrediksiKebutuhan $p): array => [
                    'bulan' => $p->periode_bulan,
                    'tahun' => $p->periode_tahun,
                    'jumlah' => $p->jumlah_prediksi,
                    'lower' => $p->confidence_lower,
                    'upper' => $p->confidence_upper,
                    'metode' => $p->metode,
                ])
                ->toArray();

            $obatRows[] = [
                'id' => $obat->id,
                'nama' => $obat->nama_obat,
                'satuan' => $obat->satuan,
            ];
            $pemakaian[(string) $obat->id] = $seri;
            $stokSekarang[(string) $obat->id] = (int) $stok;
            $modelInfo[(string) $obat->id] = $model === null ? null : [
                'status' => $model->status,
                'r2' => $model->akurasi_r2 === null ? null : (float) $model->akurasi_r2,
                'mae' => $model->mae === null ? null : (float) $model->mae,
                'mape' => $model->mape === null ? null : (float) $model->mape,
                'tanggal_training' => $model->tanggal_training?->toDateString(),
                'data_training_count' => $model->data_training_count,
            ];
            $prediksiTerakhir[(string) $obat->id] = $prediksi;
        }

        if ($obatRows === []) {
            $this->error('Tidak ada obat valid untuk diekspor.');

            return self::FAILURE;
        }

        $payload = [
            'generated_at' => now()->toDateTimeString(),
            'faskes' => [
                'id' => $faskes->id,
                'kode' => $faskes->kode_faskes,
                'nama' => $faskes->nama,
                'tipe' => $faskes->tipe,
            ],
            'periode' => [
                'mulai' => $mulai->format('Y-m'),
                'sampai' => $sampai->format('Y-m'),
            ],
            'obat' => $obatRows,
            'pemakaian' => $pemakaian,
            'stok_sekarang' => $stokSekarang,
            'model' => $modelInfo,
            'prediksi_terakhir' => $prediksiTerakhir,
        ];

        $output = (string) $this->option('output');
        $path = base_path($output);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("JSON tertulis: {$output}");
        $this->table(
            ['Obat', 'Bulan berisi', 'Total unit', 'Stok sekarang'],
            collect($obatRows)->map(fn (array $o): array => [
                $o['nama'],
                count(array_filter($pemakaian[(string) $o['id']], fn (int $v): bool => $v > 0)),
                array_sum($pemakaian[(string) $o['id']]),
                $stokSekarang[(string) $o['id']],
            ])->toArray(),
        );

        return self::SUCCESS;
    }
}
