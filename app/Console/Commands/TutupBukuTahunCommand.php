<?php

namespace App\Console\Commands;

use App\Services\TutupBukuService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TutupBukuTahunCommand extends Command
{
    protected $signature = 'tutup-buku:tahun
                            {tahun : Tahun yang akan ditutup (YYYY)}
                            {--neraca= : ID neraca_tahunan terkait (opsional)}
                            {--deadline= : Deadline YYYY-MM-DD HH:MM:SS (default 15 Jan tahun+1 23:59:59)}
                            {--catatan= : Catatan}
                            {--force : Skip konfirmasi}';

    protected $description = 'Tutup buku tahunan serentak global (deadline 15 Jan)';

    public function handle(TutupBukuService $service): int
    {
        $tahun = (int) $this->argument('tahun');

        if ($tahun < 2020 || $tahun > 2100) {
            $this->error('Tahun tidak valid.');

            return self::FAILURE;
        }

        $neracaId = $this->option('neraca') ? (int) $this->option('neraca') : null;
        $deadline = $this->option('deadline') ? Carbon::parse($this->option('deadline')) : null;

        if (! $this->option('force') && ! $this->confirm("Yakin tutup buku tahun {$tahun} (serentak global, deadline ".($deadline?->format('d/m/Y H:i') ?? ($tahun + 1).'-01-15 23:59').')?', false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        try {
            $row = $service->tutupTahun($tahun, $neracaId, auth()->id(), $this->option('catatan'), $deadline);
            $this->info("Tahun {$row->tahun} tertutup. Cutoff {$row->cutoff_at->format('Y-m-d')} deadline {$row->deadline_at->format('d/m/Y H:i')}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
