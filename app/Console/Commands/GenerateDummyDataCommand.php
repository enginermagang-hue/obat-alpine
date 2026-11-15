<?php

namespace App\Console\Commands;

use App\Services\SimulasiDummyService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateDummyDataCommand extends Command
{
    protected $signature = 'dummy:generate
                            {--months=12 : Durasi simulasi bulan (1-36, default 12)}
                            {--start-date=2024-06 : Tanggal mulai YYYY-MM (default 2024-06)}
                            {--end-date= : Tanggal akhir patokan YYYY-MM; dengan --months dihitung mundur, tanpa --months durasi dihitung dari --start-date}
                            {--modules=* : Modul: penerimaan,distribusi,pemakaian,permintaan,lplpo,rko,retur (default semua 7)}
                            {--fresh : Hapus data transaksi lama dulu (truncate + nolkan stok)}
                            {--dry-run : Preview tanpa simpan (rollback transaksi)}
                            {--seed-master : Force seed master Obat/Faskes/Supplier/SumberDana meski sudah ada}
                            {--force : Skip konfirmasi}';

    protected $description = 'Generate data dummy full simulasi (penerimaan → retur)';

    private const VALID_MODULES = ['penerimaan', 'distribusi', 'pemakaian', 'permintaan', 'lplpo', 'rko', 'retur'];

    private const ALL_MODULES = ['penerimaan', 'distribusi', 'pemakaian', 'permintaan', 'lplpo', 'rko', 'retur'];

    public function handle(SimulasiDummyService $service): int
    {
        $monthsOpt = (int) $this->option('months');
        $startDateRaw = (string) $this->option('start-date');
        $endDateRaw = $this->option('end-date');
        $modulesOpt = $this->option('modules');
        $isFresh = (bool) $this->option('fresh');
        $isDryRun = (bool) $this->option('dry-run');
        $isSeedMaster = (bool) $this->option('seed-master');
        $isForce = (bool) $this->option('force');

        $months = $monthsOpt;
        $hasMonths = $this->input->hasParameterOption('--months');
        $hasEndDate = $endDateRaw !== null && $endDateRaw !== '';

        // Validasi months
        if ($months < 1 || $months > 36) {
            $this->error('--months harus 1-36.');

            return self::FAILURE;
        }

        // Mode keduanya: end-date sebagai patokan akhir, start dihitung mundur
        if ($hasEndDate && $hasMonths) {
            try {
                $endDateInput = Carbon::parse($endDateRaw.'-01');
                if ($endDateInput->year < 2020 || $endDateInput->year > 2030) {
                    throw new \InvalidArgumentException('Tahun 2020-2030');
                }
            } catch (\Throwable $e) {
                $this->error("--end-date tidak valid (format YYYY-MM, contoh 2025-03): {$e->getMessage()}");

                return self::FAILURE;
            }

            $startDate = $endDateInput->copy()->subMonths($months - 1);
            if ($startDate->year < 2020 || $startDate->year > 2030) {
                $this->error(sprintf('Periode hasil (mulai %s) di luar rentang tahun 2020-2030. Kurangi --months atau geser --end-date.', $startDate->format('Y-m')));

                return self::FAILURE;
            }

            $this->warn('--start-date diabaikan karena --end-date + --months digunakan.');
        } elseif ($hasEndDate) {
            // Mode end-date saja: start dari --start-date, durasi dihitung
            try {
                $startDate = Carbon::parse($startDateRaw.'-01');
                if ($startDate->year < 2020 || $startDate->year > 2030) {
                    throw new \InvalidArgumentException('Tahun 2020-2030');
                }
            } catch (\Throwable $e) {
                $this->error("--start-date tidak valid (format YYYY-MM, contoh 2024-06): {$e->getMessage()}");

                return self::FAILURE;
            }

            try {
                $endDateInput = Carbon::parse($endDateRaw.'-01');
                if ($endDateInput->year < 2020 || $endDateInput->year > 2030) {
                    throw new \InvalidArgumentException('Tahun 2020-2030');
                }
            } catch (\Throwable $e) {
                $this->error("--end-date tidak valid (format YYYY-MM, contoh 2025-03): {$e->getMessage()}");

                return self::FAILURE;
            }

            if ($endDateInput->lt($startDate)) {
                $this->error('--end-date tidak boleh sebelum --start-date.');

                return self::FAILURE;
            }

            $months = (int) $startDate->diffInMonths($endDateInput) + 1;
        } else {
            // Validasi start-date
            try {
                $startDate = Carbon::parse($startDateRaw.'-01');
                if ($startDate->year < 2020 || $startDate->year > 2030) {
                    throw new \InvalidArgumentException('Tahun 2020-2030');
                }
            } catch (\Throwable $e) {
                $this->error("--start-date tidak valid (format YYYY-MM, contoh 2024-06): {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        // Normalisasi modules (support koma dan spasi)
        $modules = $this->normalizeModules($modulesOpt);
        if (empty($modules)) {
            $modules = self::ALL_MODULES;
        }
        $invalid = array_diff($modules, self::VALID_MODULES);
        if (! empty($invalid)) {
            $this->error('Modul tidak valid: '.implode(', ', $invalid).'. Valid: '.implode(', ', self::VALID_MODULES));

            return self::FAILURE;
        }

        $endDate = (clone $startDate)->addMonths($months - 1);

        // Guard production
        if (app()->isProduction() && $isFresh && ! $isForce && ! $isDryRun) {
            $this->error('Command diblokir di production untuk --fresh kecuali dengan --force. Backup dulu (backup:run --only-db).');

            return self::FAILURE;
        }

        // Informasi konfigurasi
        $this->info('Konfigurasi dummy:generate:');
        $this->info("  Periode     : {$startDate->format('M Y')} – {$endDate->format('M Y')} ({$months} bulan)");
        $this->info('  Modul       : '.implode(', ', $modules));
        $this->info('  Fresh       : '.($isFresh ? 'YA (truncate)' : 'TIDAK (append)'));
        $this->info('  Mode        : '.($isDryRun ? 'DRY-RUN (rollback)' : 'LIVE'));
        $this->newLine();

        // Seed master jika diperlukan
        if ($isSeedMaster || $this->needsMasterSeed()) {
            $this->info('Menjalankan seeder master (Obat, Faskes, Supplier, SumberDana, Stok)...');
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\FaskesSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\UserSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\ObatSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\SupplierSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\SumberDanaSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\StokGudangSeeder', '--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\AvatarPresetSeeder', '--force' => true]);
        }

        // Cek stok awal masih kosong
        if (DB::table('stok_gudang')->count() === 0) {
            $this->warn('Stok gudang kosong. Menjalankan StokGudangSeeder...');
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\StokGudangSeeder', '--force' => true]);
        }

        // Tampilkan tabel pre-flight
        $this->displayPreflight($isFresh);

        // Konfirmasi
        if (! $isForce && ! $isDryRun) {
            $modeLabel = $isFresh ? 'FRESH (hapus transaksi lama + generate baru)' : 'APPEND (tambah data baru)';
            if (! $this->confirm("Lanjutkan generate dummy {$months} bulan mode {$modeLabel}?", true)) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        // --- Fresh truncate ---
        if ($isFresh && ! $isDryRun) {
            $this->info('Menghapus data transaksi lama (truncate)...');
            $service->truncateSimulasiData();
            $this->info('Data transaksi lama di-truncate & stok di-nolkan.');
            // Re-seed sumber dana & stok gudang jika ter-truncate? SumberDana TIDAK di-truncate oleh service sekarang (hanya penggunaan), jadi aman
            // Tapi BatchStok INIT akan dibuat lagi di initStockTracking
        }

        // --- Persiapan service ---
        $service->reset();
        $service->loadReferenceData();

        if ($service->obatList->isEmpty()) {
            $this->error('Master obat kosong. Seed master dulu.');

            return self::FAILURE;
        }
        if ($service->puskesmas->isEmpty()) {
            $this->error('Master puskesmas kosong. Seed master dulu.');

            return self::FAILURE;
        }
        if ($service->suppliers->isEmpty()) {
            $this->warn('Supplier aktif kosong — penerimaan akan gagal. Seed SupplierSeeder dulu.');
        }

        $service->createFaskesUsers();
        $service->initStockTracking();
        $service->initCountersFromDb();

        $startTime = microtime(true);
        $isVerbose = $this->output->isVerbose();
        $isDecorated = $this->output->isDecorated();
        $isQuiet = $this->output->isQuiet();

        if (! $isQuiet) {
            $this->output->writeln('');
            $this->output->writeln('  <fg=cyan>╔═════════════════════════════════════╗</>');
            $this->output->writeln('  <fg=cyan>║</>       <fg=white;options=bold>Simulasi Transaksi Obat</>      <fg=cyan>║</>');
            $this->output->writeln('  <fg=cyan>║</>          <fg=gray>(dummy:generate)</>          <fg=cyan>║</>');
            $this->output->writeln('  <fg=cyan>╚═════════════════════════════════════╝</>');
            $this->output->writeln('');
        }

        if ($isDryRun) {
            DB::beginTransaction();
        }

        // Progress: default = ringkas per bulan (scroll, Windows-safe), verbose = task + detail
        for ($month = 0; $month < $months; $month++) {
            $date = (clone $startDate)->addMonths($month);
            $label = $date->format('M Y');

            $before = [
                'penerimaan' => $service->counterPenerimaan,
                'distribusi' => $service->counterDistribusi,
                'permintaan' => $service->counterPermintaan,
                'lplpo' => $service->stats['lplpo'],
                'rko' => $service->stats['rko'],
                'retur' => $service->stats['retur'],
                'pemakaian' => $service->stats['pemakaian'],
            ];

            $monthTime = microtime(true);

            if (! $isQuiet && $isVerbose) {
                $taskLabel = sprintf('Bulan %s (%d/%d)', $label, $month + 1, $months);
                $this->components->task($taskLabel, function () use ($service, $date, $month, $modules): void {
                    DB::transaction(fn () => $service->generateMonthlyData($date, $month, $modules));
                });
                $delta = $this->calcDelta($before, $service);
                $this->output->writeln('  <fg=gray>→</> '.$this->formatDelta($delta));
                $elapsedMonth = round(microtime(true) - $monthTime, 1);
                $this->output->writeln("  <fg=gray>  ⏱ {$elapsedMonth}s</>");
            } else {
                DB::transaction(fn () => $service->generateMonthlyData($date, $month, $modules));
                $delta = $this->calcDelta($before, $service);
                $elapsedMonth = round(microtime(true) - $monthTime, 1);
                if (! $isQuiet) {
                    $percent = (int) round(($month + 1) / $months * 100);
                    $bar = $this->buildBar($percent);
                    $this->output->writeln(sprintf(
                        '  %s <fg=gray>%3d%%</> <fg=white>%s</> <fg=gray>→</> %s <fg=gray>(%.1fs / %s)</>',
                        $bar,
                        $percent,
                        $label,
                        $this->formatDeltaShort($delta),
                        $elapsedMonth,
                        $this->formatMemory()
                    ));
                }
            }
        }

        if (! $isQuiet) {
            $this->newLine();
        }

        $duration = round(microtime(true) - $startTime, 2);

        if ($isDryRun) {
            DB::rollBack();
            $this->warn('Dry run: semua perubahan di-rollback (tidak ada data tersimpan).');
        } else {
            $this->info('Menyimpan stok akhir...');
            $service->persistFinalStock();
        }

        $this->printSummaryTable($service, $months, $duration, $isDryRun, $isFresh, $startDate, $endDate);

        if (! $isDryRun) {
            $this->newLine();
            $this->info('Selesai. Jalankan "php artisan ai:train-models" jika butuh prediksi AI.');
        }

        return self::SUCCESS;
    }

    private function normalizeModules(mixed $modulesOpt): array
    {
        if (empty($modulesOpt)) {
            return [];
        }

        $flat = [];
        foreach ((array) $modulesOpt as $item) {
            foreach (explode(',', (string) $item) as $part) {
                $part = strtolower(trim($part));
                if ($part !== '') {
                    $flat[] = $part;
                }
            }
        }

        return array_values(array_unique($flat));
    }

    private function needsMasterSeed(): bool
    {
        try {
            return DB::table('obat')->count() === 0
                || DB::table('fasilitas_kesehatan')->count() === 0
                || DB::table('sumber_dana')->count() === 0
                || DB::table('suppliers')->count() === 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function displayPreflight(bool $isFresh): void
    {
        $wipeTables = [
            'penerimaan_stok', 'distribusi_obat', 'pemakaian_obat',
            'permintaan_obat', 'laporan_lplpo', 'laporan_rko', 'retur_obat',
            'riwayat_stok', 'batch_stok', 'sumber_dana_penggunaan',
        ];

        $counts = [];
        foreach ($wipeTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                $counts[$table] = DB::table($table)->count();
            } catch (\Throwable $e) {
                $counts[$table] = 0;
            }
        }

        if (! empty($counts)) {
            $this->table(['Tabel transaksi', 'Jumlah '.($isFresh ? '(akan dihapus)' : '(existing)')], array_map(
                fn ($table, $count) => [$table, $count],
                array_keys($counts),
                array_values($counts)
            ));
        }

        try {
            $stokGudang = DB::table('stok_gudang')->count();
            $stokFaskes = DB::table('stok_faskes')->count();
            $this->table(['Stok agregat', 'Baris'], [
                ['stok_gudang', $stokGudang],
                ['stok_faskes', $stokFaskes],
            ]);
        } catch (\Throwable $e) {
        }
    }

    private function calcDelta(array $before, SimulasiDummyService $service): array
    {
        return [
            'penerimaan' => $service->counterPenerimaan - $before['penerimaan'],
            'distribusi' => $service->counterDistribusi - $before['distribusi'],
            'permintaan' => $service->counterPermintaan - $before['permintaan'],
            'lplpo' => $service->stats['lplpo'] - $before['lplpo'],
            'rko' => $service->stats['rko'] - $before['rko'],
            'retur' => $service->stats['retur'] - $before['retur'],
            'pemakaian' => $service->stats['pemakaian'] - $before['pemakaian'],
        ];
    }

    private function formatDelta(array $delta): string
    {
        $parts = [];
        if ($delta['penerimaan'] > 0) {
            $parts[] = "<fg=cyan>PO</> +{$delta['penerimaan']}";
        }
        if ($delta['distribusi'] > 0) {
            $parts[] = "<fg=cyan>SJ</> +{$delta['distribusi']}";
        }
        if ($delta['pemakaian'] > 0) {
            $parts[] = "<fg=green>Pakai</> +{$delta['pemakaian']}";
        }
        if ($delta['permintaan'] > 0) {
            $parts[] = "<fg=yellow>RQ</> +{$delta['permintaan']}";
        }
        if ($delta['lplpo'] > 0) {
            $parts[] = "LPLPO +{$delta['lplpo']}";
        }
        if ($delta['rko'] > 0) {
            $parts[] = "<fg=magenta>RKO +{$delta['rko']}</>";
        }
        if ($delta['retur'] > 0) {
            $parts[] = "Retur +{$delta['retur']}";
        }
        if (empty($parts)) {
            return '<fg=gray>tidak ada perubahan</>';
        }

        return implode(' <fg=gray>·</> ', $parts);
    }

    private function formatDeltaShort(array $delta): string
    {
        $parts = [];
        if ($delta['penerimaan'] > 0) {
            $parts[] = "PO +{$delta['penerimaan']}";
        }
        if ($delta['distribusi'] > 0) {
            $parts[] = "SJ +{$delta['distribusi']}";
        }
        if ($delta['pemakaian'] > 0) {
            $parts[] = "Pakai +{$delta['pemakaian']}";
        }
        if (empty($parts)) {
            return '—';
        }

        return implode(' · ', array_slice($parts, 0, 3));
    }

    private function buildBar(int $percent): string
    {
        $length = 16;
        $filled = (int) round($percent / 100 * $length);
        $empty = $length - $filled;
        if ($this->output->isDecorated()) {
            return '<fg=cyan>'.str_repeat('━', $filled).'</><fg=gray>'.str_repeat('─', $empty).'</>';
        }

        return '['.str_repeat('#', $filled).str_repeat('-', $empty).']';
    }

    private function formatMemory(): string
    {
        $bytes = memory_get_usage(true);
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1024 / 1024).' MB';
    }

    private function printSummaryTable(SimulasiDummyService $service, int $months, float $duration, bool $dryRun, bool $isFresh, Carbon $startDate, Carbon $endDate): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $this->components->info('Ringkasan Simulasi');
        $this->newLine();

        $totalPenerimaan = $service->counterPenerimaan - 1;
        $totalDistribusi = $service->counterDistribusi - 1;
        $totalPermintaan = $service->counterPermintaan - 1;

        $mode = $dryRun ? '<fg=yellow>Dry Run (rolled back)</>' : ($isFresh ? '<fg=green>Fresh (truncate + live)</>' : '<fg=cyan>Append (live)</>');
        $icon = $dryRun ? '<fg=yellow>◷</>' : '<fg=green>✔</>';

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Periode', $startDate->format('M Y').' – '.$endDate->format('M Y')." ({$months} bulan)"],
                ['Total penerimaan stok', "<fg=cyan>{$totalPenerimaan}</>"],
                ['Total distribusi obat', "<fg=cyan>{$totalDistribusi}</>"],
                ['Total pemakaian obat', '<fg=green>'.number_format($service->stats['pemakaian']).'</>'],
                ['Total permintaan obat', "<fg=yellow>{$totalPermintaan}</>"],
                ['Total LPLPO', $service->stats['lplpo']],
                ['Total RKO', $service->stats['rko'] ? "<fg=magenta>{$service->stats['rko']}</>" : $service->stats['rko']],
                ['Total retur obat', $service->stats['retur']],
                ['Waktu eksekusi', "{$duration}s"],
                ['Mode', $icon.' '.$mode],
            ],
        );
    }
}
