<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class BersihModelPrediksiCommand extends Command
{
    protected $signature = 'ai:bersih-model
                            {--status=* : Status model yang dihapus (default: kadaluarsa,gagal)}
                            {--older-than=90 : Hapus model dengan tanggal_training lebih tua dari X hari (0 = abaikan umur)}
                            {--prediksi-sebelum= : Hapus prediksi periode sebelum YYYY-MM (default: bulan berjalan)}
                            {--orphan-files : Hapus juga file ai-models/*.json tanpa baris model_prediksi}
                            {--semua : Hapus SEMUA model_prediksi + prediksi_kebutuhan + seluruh file ai-models/}
                            {--dry-run : Tampilkan jumlah yang akan dihapus tanpa eksekusi}
                            {--force : Skip konfirmasi (wajib di production)}';

    protected $description = 'Bersihkan model prediksi lama (storage + DB) dan data prediksi kedaluwarsa';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        if (app()->isProduction() && ! $isForce && ! $isDryRun) {
            $this->error('Command ini diblokir di production kecuali dengan --force. Backup database dulu (backup:run --only-db).');

            return self::FAILURE;
        }

        if ((bool) $this->option('semua')) {
            return $this->handleSemua($isDryRun, $isForce);
        }

        /** @var list<string> $statuses */
        $statuses = $this->option('status');
        if ($statuses === []) {
            $statuses = ['kadaluarsa', 'gagal'];
        }

        $olderThan = max(0, (int) $this->option('older-than'));

        $cutoff = $this->resolvePrediksiCutoff();
        if ($cutoff === null) {
            $this->error('Format --prediksi-sebelum tidak valid. Gunakan YYYY-MM, contoh: 2026-06.');

            return self::FAILURE;
        }
        [$cutoffTahun, $cutoffBulan] = $cutoff;

        $modelIds = $this->staleModelIds($statuses, $olderThan);

        $prediksiLamaCount = $this->countStalePrediksi($cutoffTahun, $cutoffBulan);
        $prediksiIkutModelCount = $modelIds === []
            ? 0
            : DB::table('prediksi_kebutuhan')->whereIn('model_id', $modelIds)->count();

        $modelPaths = $modelIds === [] ? collect()
            : DB::table('model_prediksi')->whereIn('id', $modelIds)->whereNotNull('model_path')->pluck('model_path');

        $orphanFiles = $this->option('orphan-files') ? $this->orphanModelFiles() : [];

        $this->table(['Target', 'Jumlah'], [
            ['model_prediksi (status: '.implode(',', $statuses).($olderThan > 0 ? ", >{$olderThan} hari" : '').')', count($modelIds)],
            ["prediksi_kebutuhan periode < {$cutoffTahun}-".str_pad((string) $cutoffBulan, 2, '0', STR_PAD_LEFT), $prediksiLamaCount],
            ['prediksi_kebutuhan ikut model dihapus', $prediksiIkutModelCount],
            ['file model AI (storage ai-models/)', $modelPaths->count() + count($orphanFiles)],
        ]);

        if ($isDryRun) {
            $this->info('[DRY-RUN] Tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        if (! $isForce && ! $this->confirm('Yakin hapus '.count($modelIds).' model lama + prediksi kedaluwarsa + file terkait? Aksi ini TIDAK bisa di-undo.', false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $deletedPrediksi = 0;
        $deletedModels = 0;

        DB::transaction(function () use ($modelIds, $cutoffTahun, $cutoffBulan, &$deletedPrediksi, &$deletedModels): void {
            $deletedPrediksi += DB::table('prediksi_kebutuhan')
                ->where(function ($q) use ($cutoffTahun, $cutoffBulan): void {
                    $q->where('periode_tahun', '<', $cutoffTahun)
                        ->orWhere(function ($qq) use ($cutoffTahun, $cutoffBulan): void {
                            $qq->where('periode_tahun', $cutoffTahun)
                                ->where('periode_bulan', '<', $cutoffBulan);
                        });
                })->delete();

            if ($modelIds !== []) {
                $deletedPrediksi += DB::table('prediksi_kebutuhan')->whereIn('model_id', $modelIds)->delete();
                $deletedModels += DB::table('model_prediksi')->whereIn('id', $modelIds)->delete();
            }
        });

        $deletedFiles = 0;
        foreach ($modelPaths as $path) {
            if ($path && Storage::disk('local')->exists($path) && Storage::disk('local')->delete($path)) {
                $deletedFiles++;
            }
        }
        foreach ($orphanFiles as $path) {
            if (Storage::disk('local')->delete($path)) {
                $deletedFiles++;
            }
        }

        $this->info("Selesai. Model dihapus: {$deletedModels}, prediksi dihapus: {$deletedPrediksi}, file dihapus: {$deletedFiles}.");

        return self::SUCCESS;
    }

    private function handleSemua(bool $isDryRun, bool $isForce): int
    {
        $modelCount = DB::table('model_prediksi')->count();
        $prediksiCount = DB::table('prediksi_kebutuhan')->count();
        $files = Storage::disk('local')->exists('ai-models')
            ? collect(Storage::disk('local')->files('ai-models'))
                ->filter(fn ($path) => str_ends_with($path, '.json'))
                ->values()
                ->all()
            : [];

        $this->table(['Target (MODE SEMUA)', 'Jumlah'], [
            ['model_prediksi (SEMUA)', $modelCount],
            ['prediksi_kebutuhan (SEMUA)', $prediksiCount],
            ['file model AI (seluruh ai-models/*.json)', count($files)],
        ]);

        if ($isDryRun) {
            $this->info('[DRY-RUN] Tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        if (! $isForce && ! $this->confirm("Yakin hapus SELURUH model ({$modelCount}) + prediksi ({$prediksiCount}) + file (".count($files).')? Aksi ini TIDAK bisa di-undo.', false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $deletedPrediksi = 0;
        $deletedModels = 0;

        DB::transaction(function () use (&$deletedPrediksi, &$deletedModels): void {
            $deletedPrediksi += DB::table('prediksi_kebutuhan')->delete();
            $deletedModels += DB::table('model_prediksi')->delete();
        });

        $deletedFiles = 0;
        foreach ($files as $path) {
            if (Storage::disk('local')->delete($path)) {
                $deletedFiles++;
            }
        }

        $this->info("Selesai. Model dihapus: {$deletedModels}, prediksi dihapus: {$deletedPrediksi}, file dihapus: {$deletedFiles}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $statuses
     * @return list<int>
     */
    private function staleModelIds(array $statuses, int $olderThan): array
    {
        $query = DB::table('model_prediksi')->whereIn('status', $statuses);

        if ($olderThan > 0) {
            $batas = Carbon::now()->subDays($olderThan)->toDateString();
            $query->whereDate('tanggal_training', '<', $batas);
        }

        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function countStalePrediksi(int $tahun, int $bulan): int
    {
        return DB::table('prediksi_kebutuhan')
            ->where(function ($q) use ($tahun, $bulan): void {
                $q->where('periode_tahun', '<', $tahun)
                    ->orWhere(function ($qq) use ($tahun, $bulan): void {
                        $qq->where('periode_tahun', $tahun)
                            ->where('periode_bulan', '<', $bulan);
                    });
            })->count();
    }

    /** @return array{int,int}|null */
    private function resolvePrediksiCutoff(): ?array
    {
        $raw = $this->option('prediksi-sebelum');

        if ($raw === null || $raw === '') {
            $now = Carbon::now();

            return [$now->year, $now->month];
        }

        if (! is_string($raw) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $raw, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    /** @return list<string> */
    private function orphanModelFiles(): array
    {
        if (! Storage::disk('local')->exists('ai-models')) {
            return [];
        }

        $known = Schema::hasTable('model_prediksi')
            ? DB::table('model_prediksi')->whereNotNull('model_path')->pluck('model_path')->all()
            : [];

        return collect(Storage::disk('local')->files('ai-models'))
            ->filter(fn ($path) => str_ends_with($path, '.json') && ! in_array($path, $known, true))
            ->values()
            ->all();
    }
}
