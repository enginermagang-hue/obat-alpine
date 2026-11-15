<?php

namespace App\Console\Commands;

use App\Models\BatchStok;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArsipEksporCommand extends Command
{
    protected $signature = 'arsip:ekspor
                            {--cutoff= : Batas tanggal YYYY-MM-DD (ekspor data < cutoff)}
                            {--path= : Folder storage/app path (default arsip/cutoff-YYYY-MM-DD)}';

    protected $description = 'Ekspor data arsip sebelum cut-off ke storage (batch/pemakaian/riwayat preview)';

    public function handle(): int
    {
        $cutoff = $this->option('cutoff');

        if (! $cutoff || ! strtotime($cutoff)) {
            $this->error('--cutoff wajib (YYYY-MM-DD).');

            return self::FAILURE;
        }

        $cutoffDate = Carbon::parse($cutoff)->format('Y-m-d');
        $folder = $this->option('path') ?? "arsip/cutoff-{$cutoffDate}";
        Storage::disk('local')->makeDirectory($folder);

        $counts = [
            'batch_stok (akan expired)' => BatchStok::where('tanggal_masuk', '<', $cutoffDate)->where('status', 'tersedia')->count(),
            'pemakaian_obat' => DB::table('pemakaian_obat')->where('tanggal_pemakaian', '<', $cutoffDate)->count(),
            'penerimaan_stok' => DB::table('penerimaan_stok')->where('tanggal_penerimaan', '<', $cutoffDate)->count(),
            'distribusi_obat' => DB::table('distribusi_obat')->where('tanggal_kirim', '<', $cutoffDate)->count(),
            'riwayat_stok' => DB::table('riwayat_stok')->where('tanggal', '<', $cutoffDate)->count(),
        ];

        $this->table(['Tabel', 'Jumlah < cutoff'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->toArray());

        $manifest = [
            'cutoff' => $cutoffDate,
            'generated_at' => now()->toDateTimeString(),
            'counts' => $counts,
            'note' => 'Ekspor CSV lengkap bisa via query manual atau backup mysqldump. Perintah ini hanya manifest + preview counts.',
        ];

        Storage::disk('local')->put("{$folder}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Manifest tersimpan di storage/app/{$folder}/manifest.json");
        $this->line('Untuk ekspor CSV penuh, gunakan mysqldump atau query: SELECT * FROM batch_stok WHERE tanggal_masuk < \''.$cutoffDate.'\' INTO OUTFILE ...');

        return self::SUCCESS;
    }
}
