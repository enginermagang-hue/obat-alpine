<?php

namespace App\Console\Commands;

use App\Models\BatchStok;
use App\Models\DetailPemakaianObat;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PemakaianObat;
use App\Models\StokFaskes;
use App\Models\User;
use App\Services\StokService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GeneratePemakaianPuskesmasCommand extends Command
{
    protected $signature = 'pemakaian:generate-puskesmas
                            {--tahun-akhir=2024 : Tahun akhir (patokan tabulasi, default 2024)}
                            {--tahun-mulai=2020 : Tahun mulai (default 2020)}
                            {--fasilitas= : Filter slug fasilitas, pisah koma (contoh: baun,camplong). Default semua di tabulasi}
                            {--with-stok : Buat BatchStok + update StokFaskes + catat RiwayatStok via StokService}
                            {--dry-run : Tampilkan preview tanpa insert}
                            {--force : Skip konfirmasi}';

    protected $description = 'Generate data pemakaian puskesmas 2020-2024. Tahun 2024 dari file tabulasi/*.xlsx, tahun 2020-2023 sintetis acak (±30% dari baseline 2024).';

    private const JENIS_PELAYANAN = ['rawat_jalan', 'rawat_inap', 'uks', 'posyandu', 'pusling', 'gigi', 'laboratorium', 'apotek', 'lainnya'];

    public function handle(StokService $stokService): int
    {
        $tahunAkhir = (int) $this->option('tahun-akhir');
        $tahunMulai = (int) $this->option('tahun-mulai');
        $filterFasilitas = $this->option('fasilitas');
        $withStok = (bool) $this->option('with-stok');
        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        if ($tahunMulai > $tahunAkhir) {
            $this->error("tahun-mulai ({$tahunMulai}) tidak boleh lebih besar dari tahun-akhir ({$tahunAkhir}).");

            return self::FAILURE;
        }

        if ($tahunMulai < 2000 || $tahunAkhir > 2030) {
            $this->error('Rentang tahun tidak valid (2000-2030).');

            return self::FAILURE;
        }

        // 1. Cari file tabulasi
        $tabulasiDir = base_path('tabulasi');
        if (! is_dir($tabulasiDir)) {
            $this->error("Folder tabulasi tidak ditemukan: {$tabulasiDir}");

            return self::FAILURE;
        }

        $allFiles = glob($tabulasiDir.'/*.xlsx');
        if (empty($allFiles)) {
            $this->error('Tidak ada file *.xlsx di folder tabulasi.');

            return self::FAILURE;
        }

        // Filter berdasarkan --fasilitas jika ada
        $filterSlugs = [];
        if ($filterFasilitas) {
            $filterSlugs = array_filter(array_map(fn ($s) => strtolower(trim($s)), explode(',', (string) $filterFasilitas)));
            $allFiles = array_filter($allFiles, function ($path) use ($filterSlugs): bool {
                $slug = strtolower(pathinfo($path, PATHINFO_FILENAME));
                // slug format: baun-2024, ambil prefix sebelum -2024
                $prefix = explode('-', $slug)[0] ?? $slug;

                return in_array($prefix, $filterSlugs, true);
            });
            if (empty($allFiles)) {
                $this->error('Tidak ada file yang cocok dengan filter --fasilitas='.implode(',', $filterSlugs));

                return self::FAILURE;
            }
        }

        $this->info('Tahun: '.$tahunMulai.' – '.$tahunAkhir.' ('.($tahunAkhir - $tahunMulai + 1).' tahun) | Mode: '.($withStok ? 'WITH-STOK' : 'TANPA STOK (batch_id=null)').($isDryRun ? ' | DRY-RUN' : ''));
        $this->line('File tabulasi ditemukan: '.count($allFiles));
        foreach ($allFiles as $f) {
            $this->line('  - '.basename($f));
        }

        // 2. Mapping obat kode_obat -> id
        $obatMap = Obat::pluck('id', 'kode_obat')->toArray();
        // Normalisasi key ke string
        $obatMapNormalized = [];
        foreach ($obatMap as $kode => $id) {
            $obatMapNormalized[(string) $kode] = $id;
        }

        if (empty($obatMapNormalized)) {
            $this->error('Tabel obat kosong. Seed master obat dulu.');

            return self::FAILURE;
        }

        // 3. Baca tabulasi & mapping fasilitas
        $baseline = []; // [fasilitasId => ['fasilitas'=>model,'user'=>model,'slug'=>string,'file'=>string,'data'=>[obatId=>['kode'=>..., 'bulanan'=>[1=>qty,...]]]]]
        $fasilitasIds = [];
        $obatTidakDitemukan = [];
        $fileWarnings = [];

        foreach ($allFiles as $filePath) {
            $filename = pathinfo($filePath, PATHINFO_FILENAME); // baun-2024
            $slug = strtolower(explode('-', $filename)[0] ?? $filename);

            // Cari fasilitas puskesmas dengan nama mengandung slug
            $faskes = FasilitasKesehatan::where('tipe', 'puskesmas')
                ->where(function ($q) use ($slug): void {
                    $q->whereRaw('LOWER(nama) LIKE ?', ['%'.$slug.'%'])
                        ->orWhereRaw('LOWER(kode_faskes) LIKE ?', ['%'.$slug.'%']);
                })
                ->where('status', 'aktif')
                ->first();

            if (! $faskes) {
                // Fallback: cari semua puskesmas, cocokkan slug longgar
                $allPuskesmas = FasilitasKesehatan::where('tipe', 'puskesmas')->get();
                foreach ($allPuskesmas as $p) {
                    if (str_contains(strtolower($p->nama), $slug)) {
                        $faskes = $p;
                        break;
                    }
                }
            }

            if (! $faskes) {
                $fileWarnings[] = basename($filePath).' -> fasilitas tidak ditemukan untuk slug "'.$slug.'" (skip)';

                continue;
            }

            // Cari user terhubung
            $user = User::where('fasilitas_kesehatan_id', $faskes->id)->first();
            if (! $user) {
                $user = User::whereHas('roles', fn ($q) => $q->where('name', 'puskesmas'))
                    ->where('fasilitas_kesehatan_id', $faskes->id)->first();
            }
            if (! $user) {
                $user = User::role('super_admin')->first();
            }
            if (! $user) {
                $user = User::first();
            }
            if (! $user) {
                $this->error('Tidak ada user di database. Seed user dulu.');

                return self::FAILURE;
            }

            // Baca Excel
            try {
                $spreadsheet = IOFactory::load($filePath);
            } catch (\Throwable $e) {
                $fileWarnings[] = basename($filePath).' -> gagal load Excel: '.$e->getMessage();

                continue;
            }

            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            // Parse header row 1: cari kolom bulan YYYY-MM (baseline dari file tabulasi, umumnya 2024)
            $monthColMap = []; // [bulan 1..12 => colIndex]
            $kodeObatCol = null;
            $baselineYearInFile = null;
            for ($colIdx = 1; $colIdx <= $highestColumnIndex; $colIdx++) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $header = trim((string) $sheet->getCell($colLetter.'1')->getValue());
                $headerLower = strtolower($header);
                if ($headerLower === 'kode_obat') {
                    $kodeObatCol = $colIdx;
                }
                if (preg_match('/^\d{4}-\d{2}$/', $header)) {
                    $parts = explode('-', $header);
                    $bulan = (int) $parts[1];
                    $tahunHeader = (int) $parts[0];
                    if ($bulan >= 1 && $bulan <= 12) {
                        $monthColMap[$bulan] = $colIdx;
                        $baselineYearInFile = $tahunHeader; // tahun di file, umumnya 2024
                    }
                }
            }

            if ($kodeObatCol === null) {
                $fileWarnings[] = basename($filePath).' -> kolom kode_obat tidak ditemukan (skip)';

                continue;
            }
            if (empty($monthColMap)) {
                $fileWarnings[] = basename($filePath).' -> kolom bulan YYYY-MM tidak ditemukan (skip)';

                continue;
            }

            $dataPerObat = [];
            $obatFoundInFile = 0;
            for ($row = 2; $row <= $highestRow; $row++) {
                $colLetter = Coordinate::stringFromColumnIndex($kodeObatCol);
                $kodeObatRaw = $sheet->getCell($colLetter.$row)->getValue();
                if ($kodeObatRaw === null || trim((string) $kodeObatRaw) === '') {
                    continue;
                }
                $kodeObat = trim((string) $kodeObatRaw);
                // Normalisasi: hilangkan spasi, pastikan string
                $kodeObat = preg_replace('/\s+/', '', $kodeObat);

                $obatId = $obatMapNormalized[$kodeObat] ?? null;
                if (! $obatId) {
                    $obatTidakDitemukan[$kodeObat] = ($obatTidakDitemukan[$kodeObat] ?? 0) + 1;

                    continue;
                }

                $bulanan = [];
                $hasQty = false;
                foreach ($monthColMap as $bulan => $colIdx) {
                    $letter = Coordinate::stringFromColumnIndex($colIdx);
                    $val = $sheet->getCell($letter.$row)->getCalculatedValue();
                    // Handle formula SUM atau kosong
                    if ($val === null || $val === '') {
                        $qty = 0;
                    } else {
                        // Bersihkan string numeric
                        $qty = is_numeric($val) ? (int) round((float) $val) : 0;
                        if ($qty < 0) {
                            $qty = 0;
                        }
                    }
                    $bulanan[$bulan] = $qty;
                    if ($qty > 0) {
                        $hasQty = true;
                    }
                }

                // Hanya simpan obat yang ada pemakaian di minimal 1 bulan
                if ($hasQty) {
                    $dataPerObat[$obatId] = [
                        'kode' => $kodeObat,
                        'bulanan' => $bulanan,
                    ];
                    $obatFoundInFile++;
                }
            }

            if (empty($dataPerObat)) {
                $fileWarnings[] = basename($filePath).' ('.$faskes->nama.') -> tidak ada data pemakaian valid (skip)';

                continue;
            }

            $baseline[$faskes->id] = [
                'fasilitas' => $faskes,
                'user' => $user,
                'slug' => $slug,
                'file' => basename($filePath),
                'data' => $dataPerObat,
                'obatCount' => $obatFoundInFile,
            ];
            $fasilitasIds[] = $faskes->id;

            $this->line("  ✓ {$faskes->nama} ({$slug}) -> {$obatFoundInFile} obat dari ".basename($filePath)." (user: {$user->name})");
        }

        if (empty($baseline)) {
            $this->error('Tidak ada baseline valid dari tabulasi. Periksa warning di atas.');
            foreach ($fileWarnings as $w) {
                $this->warn('  ! '.$w);
            }

            return self::FAILURE;
        }

        foreach ($fileWarnings as $w) {
            $this->warn('  ! '.$w);
        }
        if (! empty($obatTidakDitemukan)) {
            $this->warn('Kode obat di tabulasi tidak ada di master obat ( '.count($obatTidakDitemukan).' kode ): '.implode(', ', array_slice(array_keys($obatTidakDitemukan), 0, 10)).(count($obatTidakDitemukan) > 10 ? ' ...' : ''));
        }

        // 4. Cek data existing
        $existingCount = PemakaianObat::whereIn('fasilitas_id', $fasilitasIds)
            ->whereBetween('tanggal_pemakaian', [Carbon::create($tahunMulai, 1, 1)->format('Y-m-d'), Carbon::create($tahunAkhir, 12, 31)->format('Y-m-d')])
            ->count();

        if ($existingCount > 0) {
            $this->warn("Sudah ada {$existingCount} data pemakaian di rentang {$tahunMulai}-{$tahunAkhir} untuk faskes terpilih (akan ditambahkan, bukan replace).");
            $this->warn('Gunakan "pemakaian:bersih --fasilitas=... --before=..." dulu jika ingin bersih.');
        }

        // 5. Hitung estimasi total header untuk progress
        $totalBulanObat = 0;
        $totalQtyBaseline = 0;
        foreach ($baseline as $info) {
            foreach ($info['data'] as $obatInfo) {
                foreach ($obatInfo['bulanan'] as $qty) {
                    if ($qty > 0) {
                        $totalBulanObat++;
                        $totalQtyBaseline += $qty;
                    }
                }
            }
        }
        $jumlahTahun = $tahunAkhir - $tahunMulai + 1;
        // Estimasi header = totalBulanObat * jumlahTahun * ~2.5 hari rata-rata
        $estimasiHeader = (int) ($totalBulanObat * $jumlahTahun * 2.5);
        $this->info("Baseline: {$totalBulanObat} kombinasi bulan-obat dengan qty>0 di {$tahunAkhir}, total qty baseline {$totalQtyBaseline}. Estimasi header 5 tahun: ~{$estimasiHeader}");

        if ($isDryRun) {
            $this->table(['Fasilitas', 'Obat', 'Total qty 2024', 'Estimasi header 5th'], array_map(fn ($id, $info) => [
                $info['fasilitas']->nama,
                $info['obatCount'],
                array_sum(array_map(fn ($d) => array_sum($d['bulanan']), $info['data'])),
                (int) (count(array_filter(array_merge(...array_map(fn ($d) => array_values($d['bulanan']), $info['data'])), fn ($v) => $v > 0)) * $jumlahTahun * 2.5),
            ], array_keys($baseline), array_values($baseline)));

            // Simulasi acak untuk dry-run: tampilkan contoh 3 baris per faskes
            $this->line('Contoh variasi acak 2020-2023 (vs 2024):');
            $contohRows = [];
            foreach (array_slice($baseline, 0, 2, true) as $info) {
                $contohObat = array_slice($info['data'], 0, 2, true);
                foreach ($contohObat as $obatId => $d) {
                    $obat = Obat::find($obatId);
                    for ($th = $tahunMulai; $th <= $tahunAkhir; $th++) {
                        $qJan = $d['bulanan'][1] ?? 0;
                        if ($qJan === 0) {
                            continue;
                        }
                        $qSintetis = $th === $tahunAkhir ? $qJan : max(0, (int) round($qJan * random_int(70, 130) / 100 * $this->seasonMultiplier(1)));
                        $contohRows[] = [$info['fasilitas']->nama, $obat->kode_obat.' - '.substr($obat->nama_obat, 0, 20), $th.'-01', $qJan.' (2024)', $qSintetis.($th === $tahunAkhir ? ' (patokan)' : ' (acak)')];
                        if (count($contohRows) >= 6) {
                            break 2;
                        }
                    }
                }
            }
            if (! empty($contohRows)) {
                $this->table(['Faskes', 'Obat', 'Bulan', 'Baseline Jan', 'Qty Generate'], $contohRows);
            }

            $this->info('[DRY-RUN] Tidak ada data yang disimpan.');

            return self::SUCCESS;
        }

        if (! $isForce) {
            $this->warn("Akan generate ~{$estimasiHeader} pemakaian (header) untuk ".count($baseline)." puskesmas, tahun {$tahunMulai}-{$tahunAkhir}.");
            if (! $this->confirm('Lanjutkan?', true)) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        // 6. Generate
        $stats = [
            'pemakaian' => 0,
            'detail' => 0,
            'riwayat' => 0,
            'batch_created' => 0,
            'batch_reused' => 0,
            'skip_obat' => 0,
        ];

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current% [%bar%] %elapsed:6s% %memory:6s%');
        $bar->start();

        // Cache obat model untuk batch creation
        $obatCache = [];
        foreach (array_keys($obatMapNormalized) as $k) {
            // lazy load
        }

        $jenisList = self::JENIS_PELAYANAN;

        // Untuk with-stok: siapkan cache batch
        $batchCache = []; // [faskesId_obatId_year => BatchStok]

        for ($tahun = $tahunMulai; $tahun <= $tahunAkhir; $tahun++) {
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $daysInMonth = Carbon::create($tahun, $bulan, 1)->daysInMonth;
                $is2024Patokan = ($tahun === $tahunAkhir);

                foreach ($baseline as $faskesId => $info) {
                    $faskes = $info['fasilitas'];
                    $user = $info['user'];

                    foreach ($info['data'] as $obatId => $obatInfo) {
                        $baselineQty = $obatInfo['bulanan'][$bulan] ?? 0;
                        if ($baselineQty <= 0 && $is2024Patokan) {
                            continue;
                        }

                        // Tentukan qty untuk tahun ini
                        if ($is2024Patokan) {
                            $qty = $baselineQty;
                        } else {
                            if ($baselineQty <= 0) {
                                continue;
                            }
                            // Acak ±30% + musiman
                            $seasonMul = $this->seasonMultiplier($bulan);
                            $qty = (int) round($baselineQty * random_int(70, 130) / 100 * $seasonMul);
                            if ($qty < 0) {
                                $qty = 0;
                            }
                            // Untuk qty kecil, peluang 0 agar tidak terlalu penuh
                            if ($qty > 0 && $qty < 3 && random_int(1, 100) <= 20) {
                                $qty = 0;
                            }
                            if ($qty <= 0) {
                                continue;
                            }
                        }

                        // Distribusi bulanan ke harian
                        $hariPakaiCount = $qty <= 5 ? 1 : random_int(1, min($daysInMonth, 5));
                        if ($hariPakaiCount === 1) {
                            $usedDays = [random_int(1, $daysInMonth)];
                        } else {
                            $usedDays = [];
                            $attempt = 0;
                            while (count($usedDays) < $hariPakaiCount && $attempt < 50) {
                                $d = random_int(1, $daysInMonth);
                                if (! in_array($d, $usedDays, true)) {
                                    $usedDays[] = $d;
                                }
                                $attempt++;
                            }
                            sort($usedDays);
                        }

                        $perDayBase = (int) floor($qty / count($usedDays));
                        $sisa = $qty % count($usedDays);

                        foreach ($usedDays as $idx => $day) {
                            $dayQty = $perDayBase + ($idx < $sisa ? 1 : 0);
                            if ($dayQty <= 0) {
                                continue;
                            }

                            $tglPakai = Carbon::create($tahun, $bulan, min($day, $daysInMonth));

                            // Batch handling
                            $batchId = null;
                            if ($withStok) {
                                $batch = $this->ensureBatch($faskes, $obatId, $obatInfo['kode'], $tahun, $dayQty, $stats, $batchCache);
                                if (! $batch) {
                                    $stats['skip_obat']++;

                                    continue;
                                }
                                $batchId = $batch->id;
                            }

                            try {
                                DB::transaction(function () use ($faskes, $user, $tglPakai, $jenisList, $obatId, $batchId, $dayQty, $stokService, $withStok, &$stats): void {
                                    $pemakaian = PemakaianObat::create([
                                        'nomor_pemakaian' => PemakaianObat::generateNomorPemakaian($tglPakai, $faskes->id),
                                        'fasilitas_id' => $faskes->id,
                                        'tanggal_pemakaian' => $tglPakai->format('Y-m-d'),
                                        'jenis_pelayanan' => $jenisList[array_rand($jenisList)],
                                        'nama_pasien' => fake()->name(),
                                        'no_rekam_medis' => $this->generateNomorRM($tglPakai),
                                        'diagnosa_kode' => fake()->boolean(70) ? strtoupper(fake()->lexify('?')).fake()->numerify('##') : null,
                                        'user_id' => $user->id,
                                        'catatan' => null,
                                    ]);

                                    $detail = DetailPemakaianObat::create([
                                        'pemakaian_id' => $pemakaian->id,
                                        'obat_id' => $obatId,
                                        'batch_id' => $batchId,
                                        'jumlah' => $dayQty,
                                        'dosis' => null,
                                        'satuan_dosis' => null,
                                        'catatan' => null,
                                    ]);

                                    $stats['pemakaian']++;
                                    $stats['detail']++;

                                    if ($withStok && $batchId) {
                                        // Proses stok akan decrement batch + stok_faskes + buat riwayat
                                        $stokService->prosesPemakaian($pemakaian->load('details'));
                                        $stats['riwayat']++;
                                    }
                                });
                            } catch (\Throwable $e) {
                                $this->newLine();
                                $this->warn("Gagal pemakaian {$faskes->nama} {$tglPakai->format('Y-m-d')} obat {$obatInfo['kode']} qty {$dayQty}: ".$e->getMessage());
                                $stats['skip_obat']++;
                            }

                            $bar->advance();
                        }
                    }
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Jumlah'], [
            ['Tahun', $tahunMulai.' - '.$tahunAkhir],
            ['Puskesmas', count($baseline).' ('.implode(', ', array_map(fn ($i) => $i['fasilitas']->nama, $baseline)).')'],
            ['Pemakaian (header)', $stats['pemakaian']],
            ['Detail pemakaian', $stats['detail']],
            ['Riwayat stok', $stats['riwayat'].($withStok ? '' : ' (skip, tanpa --with-stok)')],
            ['Batch dibuat', $stats['batch_created']],
            ['Batch dipakai ulang', $stats['batch_reused']],
            ['Skip/gagal', $stats['skip_obat']],
        ]);

        if ($withStok) {
            $this->info('Stok faskes & batch telah diupdate via StokService. Sinkronisasi ulang tidak diperlukan.');
        } else {
            $this->warn('Mode TANPA STOK: batch_id=null, stok tidak diubah. Jalankan dengan --with-stok jika butuh RiwayatStok & stok konsisten.');
        }

        return self::SUCCESS;
    }

    private function seasonMultiplier(int $bulan): float
    {
        // Musim hujan Nov-Apr: +20% s/d +45%
        if ($bulan >= 11 || $bulan <= 4) {
            return 1.0 + random_int(20, 45) / 100;
        }

        return 1.0;
    }

    private function generateNomorRM(Carbon $date): string
    {
        return 'RM-'.$date->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Pastikan BatchStok tersedia untuk faskes+obat+tahun. Buat jika belum ada, tambah jumlah jika kurang.
     *
     * @param  array<string, BatchStok>  $cache
     */
    private function ensureBatch(FasilitasKesehatan $faskes, int $obatId, string $kodeObat, int $tahun, int $neededQty, array &$stats, array &$cache): ?BatchStok
    {
        $cacheKey = $faskes->id.'_'.$obatId.'_'.$tahun;
        if (isset($cache[$cacheKey])) {
            $batch = $cache[$cacheKey];
            // Pastikan stok batch masih cukup untuk kebutuhan hari ini
            $batch->refresh();
            if ($batch->status === 'dimusnahkan' && $batch->jumlah > 0) {
                $batch->update(['status' => 'tersedia']);
                $batch->refresh();
            }
            if ($batch->jumlah < $neededQty || $batch->status !== 'tersedia') {
                $tambah = max(0, $neededQty - $batch->jumlah) + (int) ceil($neededQty * 0.3) + 10;
                $batch->increment('jumlah', $tambah);
                if ($batch->status === 'dimusnahkan') {
                    $batch->update(['status' => 'tersedia']);
                }
                // Sync stok_faskes
                $stok = StokFaskes::firstOrCreate(
                    ['fasilitas_id' => $faskes->id, 'obat_id' => $obatId],
                    ['jumlah' => 0, 'stok_minimum' => 10]
                );
                $stok->increment('jumlah', $tambah);
                $batch->refresh();
                if ($batch->status === 'dimusnahkan') {
                    $batch->update(['status' => 'tersedia']);
                    $batch->refresh();
                }
            }

            return $batch;
        }

        $batchNumber = 'TAB-'.$kodeObat.'-'.$tahun;

        $batch = BatchStok::where('fasilitas_id', $faskes->id)
            ->where('obat_id', $obatId)
            ->where('batch_number', $batchNumber)
            ->first();

        if ($batch) {
            $stats['batch_reused']++;
            $batch->refresh();
            if ($batch->status === 'dimusnahkan' && $batch->jumlah > 0) {
                $batch->update(['status' => 'tersedia']);
                $batch->refresh();
            }
            // Pastikan jumlah cukup
            if ($batch->jumlah < $neededQty || $batch->status !== 'tersedia') {
                $tambah = max(0, $neededQty - $batch->jumlah) + (int) ceil($neededQty * 0.3) + 10;
                $batch->increment('jumlah', $tambah);
                if ($batch->status === 'dimusnahkan') {
                    $batch->update(['status' => 'tersedia']);
                }
                $stok = StokFaskes::firstOrCreate(
                    ['fasilitas_id' => $faskes->id, 'obat_id' => $obatId],
                    ['jumlah' => 0, 'stok_minimum' => 10]
                );
                $stok->increment('jumlah', $tambah);
                $batch->refresh();
            }
            if ($batch->status === 'dimusnahkan') {
                $batch->update(['status' => 'tersedia']);
                $batch->refresh();
            }
            $cache[$cacheKey] = $batch->fresh();

            return $cache[$cacheKey];
        }

        // Buat baru dengan buffer 50% + sisa tahun (estimasi kasar: neededQty * 30 hari)
        $bufferQty = max($neededQty * 5, (int) ceil($neededQty * 1.5));
        // Minimal 100 untuk obat dengan pemakaian kecil
        $initialQty = max(100, $bufferQty);

        try {
            $batch = BatchStok::create([
                'fasilitas_id' => $faskes->id,
                'obat_id' => $obatId,
                'batch_number' => $batchNumber,
                'tanggal_expired' => Carbon::create($tahun, 12, 31)->addYears(2),
                'jumlah' => $initialQty,
                'status' => 'tersedia',
                'tanggal_masuk' => Carbon::create($tahun, 1, 1),
            ]);
        } catch (\Throwable $e) {
            // Race condition: coba ambil lagi
            $batch = BatchStok::where('fasilitas_id', $faskes->id)
                ->where('obat_id', $obatId)
                ->where('batch_number', $batchNumber)
                ->first();
            if (! $batch) {
                return null;
            }
        }

        $stok = StokFaskes::firstOrCreate(
            ['fasilitas_id' => $faskes->id, 'obat_id' => $obatId],
            ['jumlah' => 0, 'stok_minimum' => 10]
        );
        $stok->increment('jumlah', $initialQty);

        $stats['batch_created']++;
        $cache[$cacheKey] = $batch;

        return $batch;
    }
}
