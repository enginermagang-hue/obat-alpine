<?php

namespace Database\Seeders;

use App\Services\SimulasiDummyService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompt\confirm;
use function Laravel\Prompt\multiselect;
use function Laravel\Prompt\select;

class SimulasiTransaksiSeeder extends Seeder
{
    public const KONSUMSI = SimulasiDummyService::KONSUMSI;

    public const OBAT_MUSIMAN = SimulasiDummyService::OBAT_MUSIMAN;

    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            FaskesSeeder::class,
            UserSeeder::class,
            ObatSeeder::class,
            SupplierSeeder::class,
            SumberDanaSeeder::class,
            StokGudangSeeder::class,
            AvatarPresetSeeder::class,
        ]);

        $config = $this->promptUserConfig();

        /** @var SimulasiDummyService $service */
        $service = app(SimulasiDummyService::class);
        $service->reset();

        if (! $config['dry_run'] && $config['truncate_existing']) {
            $service->truncateSimulasiData();
            $this->command?->info('Data simulasi lama di-truncate.');
        }

        $service->loadReferenceData();
        $service->createFaskesUsers();
        $service->initStockTracking();
        $service->initCountersFromDb();

        $startDate = Carbon::parse($config['start_date']);
        $totalMonths = $config['total_months'];
        $modules = $config['modules'];
        $dryRun = $config['dry_run'];
        $verbose = $config['verbose'];

        $endDate = (clone $startDate)->addMonths($totalMonths - 1);

        $this->command?->info('Konfigurasi simulasi:');
        $this->command?->info("  Periode     : {$startDate->format('M Y')} – {$endDate->format('M Y')} ({$totalMonths} bulan)");
        $this->command?->info('  Modul       : '.implode(', ', $modules));
        $this->command?->info('  Mode        : '.($dryRun ? 'Dry Run (tidak menyimpan)' : 'Live'));
        $this->command?->newLine();

        $startTime = microtime(true);
        $output = $this->command?->getOutput();
        $isDecorated = $output?->isDecorated() ?? false;
        $isQuiet = $output?->isQuiet() ?? false;

        if ($output && ! $isQuiet) {
            $output->writeln('');
            $output->writeln('  <fg=cyan>╔═════════════════════════════════════╗</>');
            $output->writeln('  <fg=cyan>║</>       <fg=white;options=bold>Simulasi Transaksi Obat</>      <fg=cyan>║</>');
            $output->writeln('  <fg=cyan>╚═════════════════════════════════════╝</>');
            $output->writeln('');
        }

        if ($dryRun) {
            DB::beginTransaction();
        }

        $progressBar = null;
        if ($output && ! $isQuiet && ! $verbose) {
            $progressBar = $output->createProgressBar($totalMonths);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  <fg=gray>%elapsed:6s%/%estimated:-6s% %memory:6s%</>  <fg=cyan>%message%</>');
            $progressBar->setBarCharacter('<fg=cyan>━</>');
            $progressBar->setEmptyBarCharacter('<fg=gray>─</>');
            $progressBar->setProgressCharacter('<fg=cyan>━</>');
            $progressBar->setMessage($startDate->format('M Y'));
            $progressBar->start();
        }

        for ($month = 0; $month < $totalMonths; $month++) {
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

            if ($verbose && $output && ! $isQuiet) {
                // Verbose: scroll per bulan dengan delta
                $output->writeln("  <fg=cyan>•</> <fg=white>Bulan {$label} (".($month + 1)."/{$totalMonths})</>");
                DB::transaction(fn () => $service->generateMonthlyData($date, $month, $modules));
                $delta = $this->calcDelta($before, $service);
                $output->writeln('    <fg=gray>→</> '.$this->formatDelta($delta));
            } else {
                DB::transaction(fn () => $service->generateMonthlyData($date, $month, $modules));
                if ($progressBar) {
                    $delta = $this->calcDelta($before, $service);
                    $progressBar->setMessage($label.'  <fg=gray>→ '.$this->formatDeltaShort($delta).'</>');
                    $progressBar->advance();
                }
            }
        }

        if ($progressBar) {
            $progressBar->finish();
            $output?->writeln('');
            $output?->writeln('');
        } elseif ($output && ! $isQuiet) {
            $output->writeln('');
        }

        $duration = round(microtime(true) - $startTime, 2);

        if ($dryRun) {
            DB::rollBack();
            $this->command?->warn('Dry run: semua perubahan di-rollback (tidak ada data tersimpan).');
        } else {
            $this->command?->info('Menyimpan stok akhir...');
            $service->persistFinalStock();
        }

        $this->printSummaryTable($service, $totalMonths, $duration, $dryRun);
    }

    private function promptUserConfig(): array
    {
        $defaults = [
            'start_date' => '2024-06',
            'total_months' => 24,
            'modules' => ['penerimaan', 'distribusi', 'pemakaian', 'permintaan', 'lplpo', 'retur', 'rko'],
            'dry_run' => false,
            'verbose' => true,
            'truncate_existing' => false,
        ];

        if (! $this->command) {
            return $defaults;
        }

        try {
            $startOptions = [];
            $startDate = Carbon::parse('2024-06-01');
            for ($i = 0; $i < 20; $i++) {
                $date = (clone $startDate)->addMonths($i);
                $startOptions[$date->format('Y-m')] = $date->format('M Y');
            }

            $startDateKey = select(
                label: 'Tanggal mulai simulasi',
                options: $startOptions,
                default: $defaults['start_date'],
            );

            $totalMonths = (int) select(
                label: 'Durasi simulasi (bulan)',
                options: ['6' => '6 bulan', '12' => '12 bulan', '18' => '18 bulan', '24' => '24 bulan', '36' => '36 bulan'],
                default: (string) $defaults['total_months'],
            );

            $modules = multiselect(
                label: 'Modul yang akan dijalankan',
                options: [
                    'penerimaan' => 'Penerimaan Stok Gudang',
                    'distribusi' => 'Distribusi Obat',
                    'pemakaian' => 'Pemakaian Obat',
                    'permintaan' => 'Permintaan Obat',
                    'lplpo' => 'Laporan LPLPO',
                    'retur' => 'Retur Obat',
                    'rko' => 'Rencana Kebutuhan Obat (RKO)',
                ],
                default: $defaults['modules'],
            );

            $dryRun = confirm(
                label: 'Mode dry run? (tanpa menyimpan ke database)',
                default: $defaults['dry_run'],
            );

            $verbose = confirm(
                label: 'Tampilkan detail progress per bulan?',
                default: $defaults['verbose'],
            );

            $truncateExisting = confirm(
                label: 'Hapus data simulasi lama sebelum generate? (truncate)',
                default: $defaults['truncate_existing'],
            );

            return [
                'start_date' => $startDateKey,
                'total_months' => $totalMonths,
                'modules' => $modules,
                'dry_run' => $dryRun,
                'verbose' => $verbose,
                'truncate_existing' => $truncateExisting,
            ];
        } catch (\Throwable) {
            return $defaults;
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

    private function printSummaryTable(SimulasiDummyService $service, int $totalMonths, float $duration, bool $dryRun): void
    {
        if ($this->command?->getOutput()->isQuiet()) {
            return;
        }
        $this->command?->getOutput()->writeln('');
        $this->command?->info('Ringkasan Simulasi:');
        $this->command?->newLine();

        $totalPenerimaan = $service->counterPenerimaan - 1;
        $totalDistribusi = $service->counterDistribusi - 1;
        $totalPermintaan = $service->counterPermintaan - 1;

        $mode = $dryRun ? '<fg=yellow>Dry Run (rolled back)</>' : '<fg=green>Live</>';
        $icon = $dryRun ? '<fg=yellow>◷</>' : '<fg=green>✔</>';

        $this->command->table(
            ['Metrik', 'Nilai'],
            [
                ['Bulan disimulasikan', $totalMonths],
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
