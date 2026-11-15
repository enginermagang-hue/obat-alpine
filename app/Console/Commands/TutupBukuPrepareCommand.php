<?php

namespace App\Console\Commands;

use App\Services\TutupBukuService;
use Illuminate\Console\Command;

class TutupBukuPrepareCommand extends Command
{
    protected $signature = 'tutup-buku:prepare
                            {--cutoff= : Tanggal cutoff YYYY-MM-DD}
                            {--tahun= : Tahun tutup buku tahunan (alternatif cutoff)}
                            {--dry-run : Preview tanpa eksekusi}';

    protected $description = 'Preview dampak tutup buku (cut-off inisial atau tahunan)';

    public function handle(TutupBukuService $service): int
    {
        $cutoff = $this->option('cutoff');
        $tahun = $this->option('tahun');

        if ($cutoff) {
            $preview = $service->previewInisial($cutoff);
            $this->table(['Item', 'Jumlah'], [
                ['Cutoff', $preview['cutoff']],
                ['Batch tersedia akan di-expired', $preview['batch_tersedia_akan_expired']],
                ['Stok gudang items >0', $preview['stok_gudang_items']],
                ['Stok faskes items >0', $preview['stok_faskes_items']],
            ]);

            if ($this->option('dry-run')) {
                $this->info('[DRY-RUN] Tidak ada data diubah.');
            } else {
                $this->line('Gunakan tutup-buku:inisial untuk eksekusi.');
            }

            return self::SUCCESS;
        }

        if ($tahun) {
            $tahun = (int) $tahun;
            $locked = $service->isTahunTerkunci($tahun);
            $this->info($locked ? "Tahun {$tahun} sudah tutup buku." : "Tahun {$tahun} belum tutup buku — siap ditutup (deadline 15 Jan ".($tahun + 1).').');

            return self::SUCCESS;
        }

        $this->error('Gunakan --cutoff=YYYY-MM-DD untuk inisial atau --tahun=YYYY untuk tahunan.');

        return self::FAILURE;
    }
}
