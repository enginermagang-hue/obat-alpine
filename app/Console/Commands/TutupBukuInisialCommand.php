<?php

namespace App\Console\Commands;

use App\Services\TutupBukuService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TutupBukuInisialCommand extends Command
{
    protected $signature = 'tutup-buku:inisial
                            {--cutoff= : Tanggal go-live YYYY-MM-DD}
                            {--catatan= : Catatan tutup buku}
                            {--force : Skip konfirmasi}
                            {--dry-run : Preview saja}';

    protected $description = 'Eksekusi cut-off inisial dummy -> real (soft expire batch + recalculate)';

    public function handle(TutupBukuService $service): int
    {
        $cutoff = $this->option('cutoff');

        if (! $cutoff) {
            $this->error('--cutoff wajib (YYYY-MM-DD).');

            return self::FAILURE;
        }

        if (! strtotime($cutoff)) {
            $this->error('Format --cutoff tidak valid.');

            return self::FAILURE;
        }

        $preview = $service->previewInisial($cutoff);
        $this->table(['Item', 'Jumlah'], [
            ['Cutoff', $preview['cutoff']],
            ['Batch akan di-expired', $preview['batch_tersedia_akan_expired']],
            ['Stok gudang items', $preview['stok_gudang_items']],
            ['Stok faskes items', $preview['stok_faskes_items']],
        ]);

        if ($this->option('dry-run')) {
            $this->info('[DRY-RUN] Tidak ada perubahan.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Yakin eksekusi cut-off inisial per {$preview['cutoff']}? {$preview['batch_tersedia_akan_expired']} batch akan di-expired.", false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        try {
            $result = $service->tutupInisial(Carbon::parse($cutoff), auth()->id(), $this->option('catatan'));
            $this->info("Selesai. Cutoff {$result['cutoff']} — {$result['expired_batches']} batch di-expired. Lanjutkan input Opname stok_awal per lokasi.");
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
