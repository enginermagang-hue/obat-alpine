<?php

namespace App\Console\Commands;

use App\Models\TutupBuku;
use App\Services\TutupBukuService;
use Illuminate\Console\Command;

class TutupBukuBukaCommand extends Command
{
    protected $signature = 'tutup-buku:buka
                            {tahun : Tahun yang akan dibuka kembali}
                            {--alasan= : Alasan wajib}
                            {--tutup-kembali : Tutup kembali yang sudah dibuka}
                            {--force : Skip konfirmasi}';

    protected $description = 'Buka kembali atau tutup kembali tutup buku tahunan';

    public function handle(TutupBukuService $service): int
    {
        $tahun = (int) $this->argument('tahun');
        $row = TutupBuku::where('tahun', $tahun)->first();

        if (! $row) {
            $this->error("Tutup buku tahun {$tahun} tidak ditemukan.");

            return self::FAILURE;
        }

        if ($this->option('tutup-kembali')) {
            if (! $this->option('force') && ! $this->confirm("Tutup kembali tahun {$tahun}?", false)) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }

            try {
                $service->tutupKembali($row, auth()->id());
                $this->info("Tahun {$tahun} tertutup kembali.");

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $alasan = $this->option('alasan');

        if (! $alasan) {
            $this->error('--alasan wajib untuk buka kembali.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Yakin buka kembali tahun {$tahun}? Alasan: {$alasan}", false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        try {
            $service->bukaKembali($row, $alasan, auth()->id());
            $this->info("Tahun {$tahun} dibuka kembali. Koreksi diizinkan hingga ditutup kembali.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
