<?php

namespace App\Services;

use App\Models\BatchStok;
use App\Models\DetailDistribusiObat;
use App\Models\DetailLplpo;
use App\Models\DetailPemakaianObat;
use App\Models\DetailPenerimaanStok;
use App\Models\DetailPermintaanObat;
use App\Models\DetailReturObat;
use App\Models\DetailRko;
use App\Models\DistribusiObat;
use App\Models\FasilitasKesehatan;
use App\Models\LaporanLplpo;
use App\Models\LaporanRko;
use App\Models\Obat;
use App\Models\PemakaianObat;
use App\Models\PenerimaanStok;
use App\Models\PermintaanObat;
use App\Models\ReturObat;
use App\Models\RiwayatStok;
use App\Models\StokFaskes;
use App\Models\StokGudang;
use App\Models\SumberDana;
use App\Models\SumberDanaPenggunaan;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\StokGudangSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SimulasiDummyService
{
    public Collection $obatList;

    public Collection $puskesmas;

    public Collection $pustu;

    public Collection $suppliers;

    /** @var array<int, User> indexed by fasilitas_id + sys keys */
    public array $users = [];

    /** @var array<int, int> gudangStock[obat_id] = jumlah */
    public array $gudangStock = [];

    /** @var array<int, array<int, int>> faskesStock[fasilitas_id][obat_id] = jumlah */
    public array $faskesStock = [];

    /** @var array<int, array<int, array{batch_id: int, jumlah: int}[]>> batchByFaskes[fasilitas_id][obat_id][] */
    public array $batchByFaskes = [];

    /** @var array<int, array<int, array{batch_id: int, jumlah: int}[]>> batchByGudang[obat_id][] */
    public array $batchByGudang = [];

    /** @var array<int, array<int, int>> snapshot of facility stock at start of each month (LPLPO consistency) */
    public array $faskesStockStartOfMonth = [];

    public int $counterPenerimaan = 1;

    public int $counterPermintaan = 1;

    public int $counterDistribusi = 1;

    public int $counterLPLPO = 1;

    public int $counterRetur = 1;

    public int $counterRKO = 1;

    /**
     * In-memory sequence nomor pemakaian per prefix (pengganti
     * NomorFormatService::generate() per baris yang mahal: 1 SELECT pola +
     * 1 transaksi SELECT ... FOR UPDATE per baris).
     *
     * @var array<string, int>
     */
    public array $counterPemakaian = [];

    /** @var ?string Cache pola nomor pemakaian (diambil sekali per run) */
    public ?string $polaPemakaian = null;

    /**
     * Mutasi stok bulan berjalan per faskes+obat untuk LPLPO
     * (pengganti query SUM riwayat_stok per obat).
     *
     * @var array<int, array<int, array{masuk: int, keluar: int}>>
     */
    public array $mutasiBulan = [];

    /** @var string[] Pool nama pasien (diisi sekali, pengganti fake()->name() per baris) */
    public array $poolNama = [];

    /** @var array<string, float> Cache pengali musiman per "Ym:obat_id" */
    public array $musimanCache = [];

    /** @var array<int, array<string, mixed>> Buffer baris riwayat_stok untuk bulk insert akhir bulan */
    public array $bufferRiwayat = [];

    /** @var array<string, int> Stats counters for summary */
    public array $stats = [
        'penerimaan' => 0,
        'distribusi' => 0,
        'pemakaian' => 0,
        'permintaan' => 0,
        'lplpo' => 0,
        'retur' => 0,
        'rko' => 0,
    ];

    // -----------------------------------------------------------------------
    //  Consumption baseline: [puskesmas_avg_per_month, pustu_avg_per_month]
    // -----------------------------------------------------------------------
    public const KONSUMSI = [
        // === Volume Tinggi ===
        44 => [400, 180], // Parasetamol 500mg — demam/nyeri
        11 => [280, 130], // Amoxicillin 500mg — infeksi
        7 => [120, 50],  // Amlodipin 5mg — hipertensi
        6 => [100, 40],  // Amlodipin 10mg — hipertensi
        39 => [200, 80],  // Metformin 500mg — diabetes
        34 => [180, 70],  // Captopril 25mg — hipertensi
        19 => [350, 150], // Vitamin C 50mg — suplemen
        12 => [180, 80],  // Antasida tablet — maag
        32 => [200, 90],  // Ibuprofen 400mg — nyeri

        // === Volume Sedang ===
        37 => [150, 60],  // Kotrimoksazol — infeksi
        28 => [120, 60],  // Oralit — diare
        25 => [100, 40],  // Furosemid 40mg — hipertensi
        47 => [120, 50],  // Ranitidin 150mg — maag
        43 => [100, 40],  // Omeprazole 20mg — maag
        29 => [90, 35],   // Glimepirid 2mg — diabetes
        49 => [80, 30],   // Salbutamol 2mg — asma
        36 => [100, 40],  // Prednison 5mg — antiinflamasi
        40 => [100, 45],  // Diclofenac 50mg — nyeri sendi
        51 => [90, 35],   // Simvastatin 10mg — kolesterol
        33 => [100, 40],  // Kalsium Laktat — suplemen
        53 => [80, 40],   // Zinc Sulfate — diare
        4 => [50, 20],   // Alopurinol 300mg — asam urat
        5 => [50, 20],   // Alopurinol 100mg — asam urat
        14 => [180, 80],  // Fe + Folic Acid — tablet tambah darah
        45 => [60, 25],   // Pyridoxin 25mg — vitamin B6
        1 => [80, 30],   // Albendazole 400mg — cacingan (periodik)
        15 => [60, 25],   // Loratadin 10mg — alergi

        // === Volume Rendah (Spesifik) ===
        41 => [100, 40],  // OAT FDC Kat 1 — TB (program)
        52 => [60, 20],   // Tenofovir/Lamivudin/Efavirenz — ARV
        21 => [40, 15],   // Asiklovir 200mg — herpes
        46 => [30, 10],   // Primakuin 25mg — malaria
        24 => [20, 8],    // Diazepam 5mg — psikotropika
        30 => [15, 5],    // Haloperidol 5mg — psikotropika
        48 => [50, 20],   // Vitamin A 200.000 IU — program
        20 => [60, 25],   // Vitamin C 250mg

        // === Topikal/Salep ===
        18 => [20, 10],   // Mikonazol Krim
        35 => [20, 10],   // Betametason Krim
        50 => [15, 8],    // Gentamisin Salep Mata
        23 => [12, 6],    // Asiklovir Krim

        // === Injeksi ===
        26 => [10, 4],    // Epinephrine — emergency
        42 => [8, 3],     // Oksitosin — maternal
        38 => [15, 5],    // Lidocain — anestesi lokal

        // === Sirup ===
        31 => [30, 15],   // Ibuprofen Suspensi (anak)
        8 => [20, 10],   // Amoxicillin Sirup 250mg/5mL
        9 => [15, 8],    // Amoxicillin Sirup 125mg/5mL
        10 => [12, 6],    // Amoxicillin Sirup 100mg/5mL
        13 => [30, 15],   // Antasida Suspensi
        17 => [15, 8],    // Domperidon Suspensi
        16 => [50, 20],   // Domperidon 10mg
        2 => [10, 5],    // Albendazole Susp 200mg/5mL
        3 => [10, 5],    // Albendazole Susp 400mg/5mL
        22 => [30, 12],   // Asiklovir 400mg
        27 => [20, 10],   // Fitomenadion 10mg
    ];

    // -----------------------------------------------------------------------
    //  Pola musiman: hujan (Nov-Apr: ISPA/diare/demam/malaria), kemarau
    //  (Mei-Okt: alergi/asma), dan bulan program nasional (Vit A + obat
    //  cacing Feb & Agu). OBAT_MUSIMAN dipertahankan untuk kompatibilitas.
    // -----------------------------------------------------------------------
    public const OBAT_MUSIMAN = [44, 11, 28, 37, 53, 31];

    /** Obat yang memuncak saat musim hujan (Nov-Apr). */
    public const OBAT_MUSIM_HUJAN = [44, 11, 8, 9, 10, 37, 28, 53, 31, 19, 32, 46];

    /** Obat yang memuncak saat musim kemarau (Mei-Okt). */
    public const OBAT_MUSIM_KEMARAU = [15, 49];

    /** Obat program dengan lonjakan di bulan tertentu: [obat_id => [bulan...]]. */
    public const OBAT_BULAN_PROGRAM = [
        48 => [2, 8], // Vitamin A 200.000 IU — bulan Vit A
        1 => [2, 8],  // Albendazole 400mg — bulan obat cacing
        2 => [2, 8],  // Albendazole Susp 200mg/5mL
        3 => [2, 8],  // Albendazole Susp 400mg/5mL
    ];

    /**
     * Skala global volume pemakaian obat per bulan (hardcode, default 3x).
     * Diterapkan ke baseline KONSUMSI di generatePemakaianFaskes() dan
     * getAvgConsumption() agar distribusi/permintaan/stok minimum ikut
     * seimbang dan cap stok tidak menelan kenaikan.
     */
    public const SKALA_KONSUMSI = 3.0;

    public function reset(): void
    {
        $this->gudangStock = [];
        $this->faskesStock = [];
        $this->batchByFaskes = [];
        $this->batchByGudang = [];
        $this->faskesStockStartOfMonth = [];
        $this->users = [];
        $this->counterPenerimaan = 1;
        $this->counterPermintaan = 1;
        $this->counterDistribusi = 1;
        $this->counterLPLPO = 1;
        $this->counterRetur = 1;
        $this->counterRKO = 1;
        $this->counterPemakaian = [];
        $this->polaPemakaian = null;
        $this->mutasiBulan = [];
        $this->musimanCache = [];
        $this->poolNama = [];
        $this->bufferRiwayat = [];
        $this->stats = [
            'penerimaan' => 0,
            'distribusi' => 0,
            'pemakaian' => 0,
            'permintaan' => 0,
            'lplpo' => 0,
            'retur' => 0,
            'rko' => 0,
        ];
    }

    public function loadReferenceData(): void
    {
        $this->obatList = Obat::all()->keyBy('id');
        $this->puskesmas = FasilitasKesehatan::where('tipe', 'puskesmas')->get();
        $this->pustu = FasilitasKesehatan::where('tipe', 'pustu')->get();
        $this->suppliers = Supplier::where('status', 'aktif')->get();
    }

    /**
     * Pick a SumberDana for the given year using weighted random distribution.
     * Distribution: 70% APBN (DAK + BOK), 20% APBD, 10% Dana Desa.
     */
    public function pickSumberDana(int $tahun): SumberDana
    {
        $rand = random_int(1, 100);

        if ($rand <= 70) {
            $kode = random_int(1, 2) === 1 ? "DAK-{$tahun}-01" : "BOK-{$tahun}-01";
        } elseif ($rand <= 90) {
            $kode = "APBD-{$tahun}-01";
        } else {
            $kode = "DANA-DESA-{$tahun}-01";
        }

        $sd = SumberDana::where('kode', $kode)->first();
        if (! $sd) {
            $sd = SumberDana::where('tahun', $tahun)->first()
                ?? SumberDana::inRandomOrder()->first();
        }

        if (! $sd) {
            // Auto-create fallback SumberDana if completely empty (e.g. after truncate or fresh DB)
            $sd = SumberDana::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama' => match (true) {
                        str_starts_with($kode, 'DAK') => "DAK Fisik Bidang Kesehatan {$tahun}",
                        str_starts_with($kode, 'BOK') => "BOK Puskesmas {$tahun}",
                        str_starts_with($kode, 'APBD') => "APBD Pengadaan Obat {$tahun}",
                        default => "Dana Desa Bidang Kesehatan {$tahun}",
                    },
                    'tahun' => $tahun,
                    'total_anggaran' => 1_000_000_000,
                    'status' => 'aktif',
                ]
            );
        }

        return $sd;
    }

    public function createFaskesUsers(): void
    {
        $faskesAll = $this->puskesmas->merge($this->pustu);

        foreach ($faskesAll as $faskes) {
            $email = 'faskes_'.$faskes->id.'@mail.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => "Petugas {$faskes->nama}",
                    'password' => '123',
                    'email_verified_at' => now(),
                    'fasilitas_kesehatan_id' => $faskes->id,
                ],
            );

            $role = $faskes->tipe === 'puskesmas' ? 'puskesmas' : 'pustu';
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $this->users[$faskes->id] = $user;
        }

        $sysUserDefs = [
            ['email' => 'admin_gudang@mail.com', 'name' => 'Admin Gudang', 'role' => 'admin_gudang'],
            ['email' => 'admin_dinas@mail.com', 'name' => 'Admin Dinas', 'role' => 'admin_dinas'],
            ['email' => 'superadmin@mail.com', 'name' => 'Super Admin', 'role' => 'super_admin'],
        ];

        foreach ($sysUserDefs as $def) {
            $user = User::firstOrCreate(
                ['email' => $def['email']],
                [
                    'name' => $def['name'],
                    'password' => '123',
                    'email_verified_at' => now(),
                    'fasilitas_kesehatan_id' => null,
                ],
            );

            $role = Role::firstOrCreate(['name' => $def['role'], 'guard_name' => 'web']);

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $this->users['sys_'.$user->email] = $user;
        }
    }

    public function initCountersFromDb(): void
    {
        $maxSJ = DistribusiObat::max('nomor_surat_jalan');
        if ($maxSJ && preg_match('/(\d{4})$/', $maxSJ, $m)) {
            $this->counterDistribusi = max($this->counterDistribusi, (int) $m[1] + 1);
        }
        $maxPO = PenerimaanStok::max('nomor_penerimaan');
        if ($maxPO && preg_match('/(\d{4})$/', $maxPO, $m)) {
            $this->counterPenerimaan = max($this->counterPenerimaan, (int) $m[1] + 1);
        }
        $maxRQ = PermintaanObat::max('nomor_permintaan');
        if ($maxRQ && preg_match('/(\d{4})$/', $maxRQ, $m)) {
            $this->counterPermintaan = max($this->counterPermintaan, (int) $m[1] + 1);
        }
        $maxLPLPO = LaporanLplpo::max('id');
        if ($maxLPLPO) {
            $this->counterLPLPO = max($this->counterLPLPO, $maxLPLPO + 1);
        }
        $maxRKO = LaporanRko::max('id');
        if ($maxRKO) {
            $this->counterRKO = max($this->counterRKO, $maxRKO + 1);
        }
        $maxRetur = ReturObat::max('id');
        if ($maxRetur) {
            $this->counterRetur = max($this->counterRetur, $maxRetur + 1);
        }
    }

    public function truncateSimulasiData(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } else {
            DB::statement('PRAGMA foreign_keys=OFF');
        }

        foreach (['detail_retur_obat', 'retur_obat', 'detail_rko', 'laporan_rko', 'detail_lplpo', 'laporan_lplpo', 'detail_permintaan_obat', 'permintaan_obat', 'detail_pemakaian_obat', 'pemakaian_obat', 'detail_distribusi_obat', 'distribusi_obat', 'detail_penerimaan_stok', 'penerimaan_stok', 'riwayat_stok', 'sumber_dana_penggunaan'] as $table) {
            try {
                DB::table($table)->truncate();
            } catch (\Throwable $e) {
                // sqlite truncate via delete
                try {
                    DB::table($table)->delete();
                } catch (\Throwable $e2) {
                }
            }
        }

        // Nolkan stok agregat (stok_minimum dipertahankan)
        try {
            DB::table('stok_gudang')->update(['jumlah' => 0]);
            DB::table('stok_faskes')->update(['jumlah' => 0]);
        } catch (\Throwable $e) {
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            DB::statement('PRAGMA foreign_keys=ON');
        }

        // Reset auto increment untuk MySQL, sqlite_sequence untuk sqlite
        try {
            if ($driver === 'mysql') {
                foreach (['detail_retur_obat', 'retur_obat', 'detail_rko', 'laporan_rko', 'detail_lplpo', 'laporan_lplpo', 'detail_permintaan_obat', 'permintaan_obat', 'detail_pemakaian_obat', 'pemakaian_obat', 'detail_distribusi_obat', 'distribusi_obat', 'detail_penerimaan_stok', 'penerimaan_stok', 'riwayat_stok', 'sumber_dana_penggunaan'] as $table) {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                }
            } else {
                DB::table('sqlite_sequence')->whereIn('name', ['detail_retur_obat', 'retur_obat', 'detail_rko', 'laporan_rko', 'detail_lplpo', 'laporan_lplpo', 'detail_permintaan_obat', 'permintaan_obat', 'detail_pemakaian_obat', 'pemakaian_obat', 'detail_distribusi_obat', 'distribusi_obat', 'detail_penerimaan_stok', 'penerimaan_stok', 'riwayat_stok', 'sumber_dana_penggunaan'])->delete();
            }
        } catch (\Throwable $e) {
        }
    }

    public function initStockTracking(): void
    {
        // Jika stok gudang kosong / nol (setelah --fresh truncate), reseed dari StokGudangSeeder
        if (StokGudang::count() === 0 || (int) StokGudang::sum('jumlah') === 0) {
            try {
                // Hapus baris nol agar firstOrCreate bisa insert dengan jumlah awal yang benar
                if (StokGudang::count() > 0) {
                    DB::table('stok_gudang')->delete();
                }
                (new StokGudangSeeder)->run();
            } catch (\Throwable $e) {
            }
        }

        $stokGudang = StokGudang::all();
        foreach ($stokGudang as $sg) {
            $this->gudangStock[$sg->obat_id] = (int) $sg->jumlah;

            $sumberDanaAwal = $this->pickSumberDana(2024);

            $batchNumberInit = 'INIT/'.str_pad((string) $sg->obat_id, 3, '0', STR_PAD_LEFT).'/JUN2024';
            $batch = BatchStok::firstOrCreate(
                ['batch_number' => $batchNumberInit],
                [
                    'penerimaan_id' => null,
                    'fasilitas_id' => null,
                    'sumber_dana_id' => $sumberDanaAwal->id,
                    'obat_id' => $sg->obat_id,
                    'tanggal_expired' => '2026-12-31',
                    'jumlah' => (int) $sg->jumlah,
                    'status' => 'tersedia',
                    'tanggal_masuk' => '2024-06-01',
                    'harga_beli' => 0,
                ]
            );

            $this->batchByGudang[$sg->obat_id] = [[
                'batch_id' => $batch->id,
                'jumlah' => (int) $sg->jumlah,
            ]];
        }

        foreach ($this->puskesmas as $p) {
            $this->faskesStock[$p->id] = [];
            $this->batchByFaskes[$p->id] = [];
        }
        foreach ($this->pustu as $p) {
            $this->faskesStock[$p->id] = [];
            $this->batchByFaskes[$p->id] = [];
        }

        $this->faskesStockStartOfMonth = [];
    }

    // =======================================================================
    //  MONTHLY GENERATOR
    // =======================================================================

    public function generateMonthlyData(Carbon $date, int $monthIndex, array $modules): void
    {
        // Reset accumulator mutasi (LPLPO) — sebelum distribusi awal agar
        // mutasi awal bulan ikut tercatat seperti query SUM sebelumnya.
        $this->mutasiBulan = [];

        if ($monthIndex < 2) {
            $this->distributeInitialStock($date);
        }

        $this->snapshotFaskesStock();

        if (in_array('penerimaan', $modules)) {
            $this->generatePenerimaanGudang($date);
        }

        if (in_array('distribusi', $modules) && $monthIndex >= 2) {
            $this->generateAllDistribusi($date);
        }

        if (in_array('pemakaian', $modules)) {
            $this->generatePemakaian($date);
        }

        if (in_array('permintaan', $modules)) {
            $this->generateAllPermintaan($date);
        }

        if (in_array('lplpo', $modules)) {
            $this->generateAllLPLPO($date);
        }

        if (in_array('rko', $modules) && $date->month == 12) {
            // Flush dulu: RKO membaca riwayat_stok setahun dari DB,
            // termasuk baris bulan Desember yang masih ter-buffer.
            $this->flushRiwayatBuffer();
            $this->generateAllRKO($date);
        }

        if (in_array('retur', $modules) && $monthIndex > 0 && $monthIndex % 3 === 0) {
            $this->generateAllRetur($date);
        }

        // Tulis semua baris riwayat_stok yang ter-buffer (bulk insert, 1x per bulan).
        $this->flushRiwayatBuffer();
    }

    public function snapshotFaskesStock(): void
    {
        $this->faskesStockStartOfMonth = [];
        foreach ($this->faskesStock as $fasilitasId => $stocks) {
            foreach ($stocks as $obatId => $jumlah) {
                $this->faskesStockStartOfMonth[$fasilitasId][$obatId] = $jumlah;
            }
        }
    }

    /**
     * Pengali musiman per obat untuk bulan berjalan. Dihitung sekali per
     * (bulan, obat) lalu di-cache agar sefaskes maupun antar modul
     * (pemakaian, distribusi, permintaan) melihat gelombang yang sama.
     */
    public function getMusimanMultiplier(Carbon $date, int $obatId): float
    {
        $key = $date->format('Ym').':'.$obatId;
        if (! isset($this->musimanCache[$key])) {
            $month = $date->month;
            $mul = 1.0;

            if (isset(self::OBAT_BULAN_PROGRAM[$obatId]) && in_array($month, self::OBAT_BULAN_PROGRAM[$obatId])) {
                $mul = 2.5 + random_int(0, 50) / 100;
            } elseif (in_array($obatId, self::OBAT_MUSIM_HUJAN) && ($month >= 11 || $month <= 4)) {
                $mul = 1.0 + random_int(20, 45) / 100;
            } elseif (in_array($obatId, self::OBAT_MUSIM_KEMARAU) && $month >= 5 && $month <= 10) {
                $mul = 1.0 + random_int(15, 30) / 100;
            }

            $this->musimanCache[$key] = $mul;
        }

        return $this->musimanCache[$key];
    }

    // =======================================================================
    //  1. PENERIMAAN STOK (Gudang)
    // =======================================================================

    public function generatePenerimaanGudang(Carbon $date): void
    {
        $totalReceipts = random_int(2, 4);
        $adminGudang = $this->users['sys_admin_gudang@mail.com'];
        $now = now()->toDateTimeString();

        for ($r = 0; $r < $totalReceipts; $r++) {
            $supplier = $this->suppliers->random();
            $tglTerima = (clone $date)->addDays(random_int(1, 25));
            $sumberDana = $this->pickSumberDana($date->year);

            $penerimaan = PenerimaanStok::create([
                'nomor_penerimaan' => 'PO/'.$date->format('Y/m').'/'.str_pad((string) $this->counterPenerimaan++, 4, '0', STR_PAD_LEFT),
                'tipe' => 'pembelian',
                'supplier_id' => $supplier->id,
                'sumber_dana_id' => $sumberDana->id,
                'nomor_po' => 'PO-'.$date->format('Ymd').'-'.strtoupper(substr($supplier->nama, 0, 3)).'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'nomor_invoice' => 'INV/'.$date->format('Y/m').'/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'tanggal_penerimaan' => $tglTerima,
                'fasilitas_id' => null,
                'user_id' => $adminGudang->id,
                'status' => 'dikonfirmasi',
                'catatan' => 'Penerimaan bulan '.$date->format('F Y'),
                'total_biaya' => 0,
            ]);

            // Cakupan diperlebar (15-25 dari ~53 obat) agar gudang mampu
            // memasok distribusi full-sweep ke 6 faskes tiap bulan.
            // Prioritas: obat yang stok gudangnya di bawah ~1 bulan kebutuhan
            // sistem (6 faskes) wajib ikut agar gudang tidak kering dan
            // cakupan pemakaian tidak bolong; sisanya diisi acak.
            $drugCount = random_int(15, 25);
            $needRefill = [];
            foreach ($this->obatList->keys()->all() as $obatId) {
                [$avgPusk] = self::KONSUMSI[$obatId] ?? [50, 20];
                $sebulanSistem = (int) ($avgPusk * self::SKALA_KONSUMSI * 6);
                if (($this->gudangStock[$obatId] ?? 0) < $sebulanSistem) {
                    $needRefill[] = $obatId;
                }
            }
            shuffle($needRefill);
            $selectedIds = array_slice($needRefill, 0, $drugCount);
            if (count($selectedIds) < $drugCount) {
                $rest = array_values(array_diff($this->obatList->keys()->all(), $selectedIds));
                shuffle($rest);
                $selectedIds = array_merge($selectedIds, array_slice($rest, 0, $drugCount - count($selectedIds)));
            }
            $selectedObat = $this->obatList->only($selectedIds);
            $totalBiaya = 0;
            $jumlahObat = 0;

            foreach ($selectedObat as $obat) {
                $qty = $this->getPenerimaanQty($obat->id);

                if ($qty <= 0) {
                    continue;
                }

                $hargaSatuan = $obat->harga_satuan ?: $this->estimateHarga($obat->id);
                $subTotal = $qty * $hargaSatuan;
                $totalBiaya += $subTotal;
                $jumlahObat++;

                $batchNumber = 'BCH-'.$date->format('Ym').'-'.strtoupper(substr($obat->kode_obat, -4)).'-'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT);
                $tglExpired = (clone $date)->addMonths(random_int(12, 36))->addDays(random_int(1, 28));

                $batch = BatchStok::create([
                    'penerimaan_id' => $penerimaan->id,
                    'fasilitas_id' => null,
                    'sumber_dana_id' => $sumberDana->id,
                    'obat_id' => $obat->id,
                    'batch_number' => $batchNumber,
                    'tanggal_expired' => $tglExpired,
                    'jumlah' => $qty,
                    'status' => 'tersedia',
                    'tanggal_masuk' => $tglTerima,
                    'harga_beli' => $hargaSatuan,
                ]);

                DetailPenerimaanStok::create([
                    'penerimaan_id' => $penerimaan->id,
                    'obat_id' => $obat->id,
                    'batch_number' => $batchNumber,
                    'tanggal_expired' => $tglExpired,
                    'jumlah' => $qty,
                    'harga_satuan' => $hargaSatuan,
                    'sub_total' => $subTotal,
                    'keterangan' => null,
                ]);

                $this->gudangStock[$obat->id] = ($this->gudangStock[$obat->id] ?? 0) + $qty;
                $this->batchByGudang[$obat->id][] = [
                    'batch_id' => $batch->id,
                    'jumlah' => $qty,
                ];

                $this->bufferRiwayat[] = [
                    'fasilitas_id' => null,
                    'obat_id' => $obat->id,
                    'tipe' => 'masuk',
                    'jumlah' => $qty,
                    'stok_sebelum' => $this->gudangStock[$obat->id] - $qty,
                    'stok_sesudah' => $this->gudangStock[$obat->id],
                    'referensi_type' => PenerimaanStok::class,
                    'referensi_id' => $penerimaan->id,
                    'user_id' => $adminGudang->id,
                    'keterangan' => 'Penerimaan dari '.$supplier->nama,
                    'tanggal' => $tglTerima->toDateTimeString(),
                ];
            }

            $penerimaan->update(['total_biaya' => $totalBiaya]);

            if ($totalBiaya > 0) {
                SumberDanaPenggunaan::create([
                    'sumber_dana_id' => $sumberDana->id,
                    'rko_id' => null,
                    'fasilitas_id' => null,
                    'tipe' => 'realisasi',
                    'jumlah_obat' => $jumlahObat,
                    'total_biaya' => $totalBiaya,
                    'tanggal' => $tglTerima,
                    'keterangan' => 'Realisasi penerimaan dari '.$supplier->nama.' ('.$sumberDana->kode.')',
                ]);
            }
        }

        $this->stats['penerimaan'] += $totalReceipts;
    }

    private function getPenerimaanQty(int $obatId): int
    {
        // Diskalakan ~3x mengikuti SKALA_KONSUMSI agar gudang tidak tekor.
        return match ($obatId) {
            44 => random_int(6000, 15000),
            11 => random_int(4500, 12000),
            7, 6 => random_int(3000, 9000),
            39 => random_int(3000, 7500),
            34 => random_int(2400, 6000),
            19 => random_int(6000, 12000),
            12 => random_int(2400, 6000),
            32 => random_int(3000, 7500),
            37 => random_int(1500, 4500),
            28 => random_int(1500, 4500),
            25, 47, 43 => random_int(1500, 4500),
            14 => random_int(3000, 6000),
            41 => random_int(1500, 4500),
            52 => random_int(900, 2400),
            default => random_int(600, 3000),
        };
    }

    private function estimateHarga(int $obatId): float
    {
        return match ($obatId) {
            44 => 150,
            11 => 300,
            6, 7 => 500,
            39 => 400,
            34 => 250,
            19 => 100,
            28 => 2000,
            26 => 5000,
            42 => 8000,
            38 => 3000,
            41 => 2500,
            default => 1000,
        };
    }

    // =======================================================================
    //  2. PEMAKAIAN OBAT
    // =======================================================================

    public function generatePemakaian(Carbon $date): void
    {
        foreach ($this->puskesmas as $puskesmas) {
            $this->generatePemakaianFaskes($puskesmas, $date, 'puskesmas');
        }
        foreach ($this->pustu as $pustu) {
            $this->generatePemakaianFaskes($pustu, $date, 'pustu');
        }
    }

    private function generatePemakaianFaskes(FasilitasKesehatan $faskes, Carbon $date, string $tipe): void
    {
        $user = $this->users[$faskes->id] ?? null;
        if (! $user) {
            return;
        }

        // Full coverage: SEMUA obat master dipakai tiap faskes tiap bulan
        // (bukan sampel acak), dengan fallback rata-rata untuk id yang
        // belum ada di KONSUMSI agar tidak ada obat yang nol pemakaian.
        $obatIds = $this->obatList->keys()->all();

        $jenisPelayananList = ['rawat_jalan', 'rawat_inap', 'uks', 'posyandu', 'pusling', 'gigi', 'laboratorium', 'apotek', 'lainnya'];

        $daysInMonth = (clone $date)->daysInMonth;
        $now = now()->toDateTimeString();

        foreach ($obatIds as $obatId) {
            [$avgPuskesmas, $avgPustu] = self::KONSUMSI[$obatId] ?? [50, 20];
            $avg = (int) (($tipe === 'puskesmas' ? $avgPuskesmas : $avgPustu) * self::SKALA_KONSUMSI);

            $actualAvg = (int) ($avg * $this->getMusimanMultiplier($date, $obatId));

            $jumlah = max(1, (int) ($actualAvg * random_int(70, 130) / 100));

            $currentStock = $this->faskesStock[$faskes->id][$obatId] ?? 0;
            if ($jumlah > $currentStock) {
                $jumlah = max(0, $currentStock);
            }
            if ($jumlah <= 0) {
                continue;
            }

            $hariPakai = random_int(1, min($daysInMonth, 10));
            $usedDays = [];
            for ($d = 0; $d < $hariPakai; $d++) {
                $usedDays[] = random_int(1, $daysInMonth);
            }
            $usedDays = array_values(array_unique($usedDays));
            $perDay = max(1, (int) ($jumlah / count($usedDays)));

            foreach ($usedDays as $day) {
                $dayQty = $perDay;
                $tglPakai = (clone $date)->setDay(min($day, $daysInMonth));

                $batchInfo = $this->pickBatch($faskes->id, $obatId, $dayQty);
                if ($batchInfo === null) {
                    continue;
                }

                // insertGetId: 1 query tanpa hidrasi model/event (buffer riwayat di-flush bulk akhir bulan).
                $pemakaianId = DB::table('pemakaian_obat')->insertGetId([
                    'nomor_pemakaian' => $this->nextNomorPemakaian($tglPakai),
                    'fasilitas_id' => $faskes->id,
                    'tanggal_pemakaian' => $tglPakai->toDateString(),
                    'jenis_pelayanan' => $jenisPelayananList[array_rand($jenisPelayananList)],
                    'nama_pasien' => $this->randomNamaPasien(),
                    'no_rekam_medis' => $this->generateNomorRM($tglPakai),
                    'diagnosa_kode' => $this->randomDiagnosa(),
                    'user_id' => $user->id,
                    'catatan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $detailId = DB::table('detail_pemakaian_obat')->insertGetId([
                    'pemakaian_id' => $pemakaianId,
                    'obat_id' => $obatId,
                    'batch_id' => $batchInfo['batch_id'],
                    'jumlah' => $dayQty,
                    'dosis' => null,
                    'satuan_dosis' => null,
                    'catatan' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $stokSebelum = $this->faskesStock[$faskes->id][$obatId] ?? 0;
                $this->faskesStock[$faskes->id][$obatId] = $stokSebelum - $dayQty;
                $this->catatMutasi($faskes->id, $obatId, 'keluar', $dayQty);

                $this->bufferRiwayat[] = [
                    'fasilitas_id' => $faskes->id,
                    'obat_id' => $obatId,
                    'tipe' => 'keluar',
                    'jumlah' => -$dayQty,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSebelum - $dayQty,
                    'referensi_type' => DetailPemakaianObat::class,
                    'referensi_id' => $detailId,
                    'user_id' => $user->id,
                    'keterangan' => 'Pemakaian '.$tglPakai->format('d M Y'),
                    'tanggal' => $tglPakai->toDateTimeString(),
                ];

                $this->stats['pemakaian']++;
            }
        }
    }

    private function generateNomorRM(Carbon $date): string
    {
        return 'RM-'.$date->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor pemakaian tanpa query per baris. Fast path memakai counter
     * in-memory per prefix (format default PMK-{FASKES}-{YYYYMM}-{Urut:4}
     * dengan FASKES=GUD, sama seperti generateNomorPemakaian() tanpa
     * fasilitas id). Seed awal diambil dari MAX sekali per prefix agar
     * mode append tetap unik. Jika pola dikustom via PengaturanLaporan,
     * fallback ke NomorFormatService per baris agar tetap benar.
     */
    private function nextNomorPemakaian(Carbon $tglPakai): string
    {
        $this->polaPemakaian ??= NomorFormatService::getPattern('pemakaian_obat');

        if ($this->polaPemakaian === NomorFormatService::DOCUMENTS['pemakaian_obat']['default']) {
            $prefix = 'PMK-GUD-'.$tglPakai->format('Ym').'-';
            if (! isset($this->counterPemakaian[$prefix])) {
                $max = PemakaianObat::where('nomor_pemakaian', 'like', $prefix.'%')->max('nomor_pemakaian');
                $start = 0;
                if (is_string($max) && preg_match('/(\d+)$/', $max, $m)) {
                    $start = (int) $m[1];
                }
                $this->counterPemakaian[$prefix] = $start;
            }

            return $prefix.str_pad((string) ++$this->counterPemakaian[$prefix], 4, '0', STR_PAD_LEFT);
        }

        return PemakaianObat::generateNomorPemakaian($tglPakai);
    }

    private function catatMutasi(?int $faskesId, int $obatId, string $arah, int $qty): void
    {
        if ($faskesId === null || $qty <= 0) {
            return;
        }
        if (! isset($this->mutasiBulan[$faskesId][$obatId])) {
            $this->mutasiBulan[$faskesId][$obatId] = ['masuk' => 0, 'keluar' => 0];
        }
        $this->mutasiBulan[$faskesId][$obatId][$arah] += $qty;
    }

    private function randomNamaPasien(): string
    {
        if (empty($this->poolNama)) {
            for ($i = 0; $i < 300; $i++) {
                $this->poolNama[] = fake()->name();
            }
        }

        return $this->poolNama[array_rand($this->poolNama)];
    }

    private function flushRiwayatBuffer(): void
    {
        if (empty($this->bufferRiwayat)) {
            return;
        }
        foreach (array_chunk($this->bufferRiwayat, 1000) as $chunk) {
            RiwayatStok::insert($chunk);
        }
        $this->bufferRiwayat = [];
    }

    private function pickBatch(int $fasilitasId, int $obatId, int $jumlah): ?array
    {
        $batches = $this->batchByFaskes[$fasilitasId][$obatId] ?? [];

        if (empty($batches)) {
            return null;
        }

        usort($batches, fn ($a, $b) => $a['batch_id'] <=> $b['batch_id']);

        $remaining = $jumlah;
        $lastBatchId = null;

        foreach ($batches as &$batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch['jumlah'], $remaining);
            $batch['jumlah'] -= $take;
            $remaining -= $take;
            $lastBatchId = $batch['batch_id'];
        }

        if ($remaining > 0 || $lastBatchId === null) {
            return null;
        }

        $batches = array_values(array_filter($batches, fn ($b) => $b['jumlah'] > 0));
        $this->batchByFaskes[$fasilitasId][$obatId] = $batches;

        return ['batch_id' => $lastBatchId];
    }

    private function randomDiagnosa(): string
    {
        $diagnosa = [
            'A00', 'A01', 'A02', 'A03', 'A04', 'A05', 'A06', 'A07', 'A08', 'A09',
            'B00', 'B01', 'B02', 'B05', 'B15', 'B16', 'B17', 'B18', 'B19',
            'E10', 'E11', 'E14',
            'I10', 'I11', 'I15',
            'J00', 'J01', 'J02', 'J03', 'J04', 'J05', 'J06', 'J15', 'J18',
            'K21', 'K25', 'K26', 'K27', 'K29', 'K30',
            'L00', 'L01', 'L02', 'L03', 'L08',
            'M00', 'M01', 'M02', 'M05', 'M06', 'M10', 'M15', 'M17', 'M25',
            'N10', 'N11', 'N12', 'N30', 'N39', 'N40',
            'R50', 'R51', 'R52', 'R53', 'R55', 'R10', 'R11',
        ];

        return $diagnosa[array_rand($diagnosa)].'.'.random_int(0, 9);
    }

    // =======================================================================
    //  3. PERMINTAAN OBAT
    // =======================================================================

    public function generateAllPermintaan(Carbon $date): void
    {
        foreach ($this->pustu as $pustu) {
            $indukId = $pustu->puskesmas_induk_id;
            if (! $indukId) {
                continue;
            }
            $this->generatePermintaan($pustu->id, $indukId, 'pustu_ke_puskesmas', $date);
        }

        foreach ($this->puskesmas as $puskesmas) {
            $this->generatePermintaan($puskesmas->id, null, 'puskesmas_ke_dinas', $date);
        }
    }

    private function generatePermintaan(int $pengirimId, ?int $tujuanId, string $tipe, Carbon $date): void
    {
        $stok = $this->faskesStock[$pengirimId] ?? [];

        $obatButuh = [];
        foreach ($stok as $obatId => $jumlah) {
            if ($jumlah < $this->getStokMinimum($obatId, $pengirimId)) {
                $obatButuh[] = $obatId;
            }
        }

        $allRequested = array_unique(array_merge(
            $obatButuh,
            $this->obatList->random(min(random_int(2, 6), $this->obatList->count()))->pluck('id')->toArray(),
        ));

        $allRequested = array_slice($allRequested, 0, random_int(5, 15));
        if (empty($allRequested)) {
            return;
        }

        $petugas = $this->users[$pengirimId] ?? $this->users['sys_admin_gudang@mail.com'];
        $tglPermintaan = (clone $date)->addDays(random_int(5, 20));

        $permintaan = PermintaanObat::create([
            'nomor_permintaan' => 'RQ/'.$date->format('Y/m').'/'.str_pad((string) $this->counterPermintaan++, 4, '0', STR_PAD_LEFT),
            'fasilitas_pengirim_id' => $pengirimId,
            'fasilitas_tujuan_id' => $tujuanId,
            'tipe_permintaan' => $tipe,
            'lplpo_id' => null,
            'status' => 'diterima',
            'tanggal_permintaan' => $tglPermintaan,
            'tanggal_disetujui' => (clone $tglPermintaan)->addDays(random_int(1, 3)),
            'tanggal_dikirim' => (clone $tglPermintaan)->addDays(random_int(3, 7)),
            'tanggal_diterima' => (clone $tglPermintaan)->addDays(random_int(5, 10)),
            'disetujui_oleh' => $this->users['sys_admin_dinas@mail.com']->id,
            'catatan' => null,
        ]);

        foreach ($allRequested as $obatId) {
            $currentStock = $this->faskesStock[$pengirimId][$obatId] ?? 0;
            $avgConsumption = $this->getAvgConsumption($obatId, $pengirimId) * $this->getMusimanMultiplier($date, $obatId);
            $diminta = max(50, (int) ($avgConsumption * random_int(15, 30) / 10) - $currentStock);
            $diminta = max($diminta, random_int(50, 200));

            $disetujui = (int) ($diminta * random_int(70, 100) / 100);
            $dikirim = (int) ($disetujui * random_int(80, 100) / 100);
            $diterima = (int) ($dikirim * random_int(90, 100) / 100);

            DetailPermintaanObat::create([
                'permintaan_id' => $permintaan->id,
                'obat_id' => $obatId,
                'jumlah_diminta' => $diminta,
                'jumlah_disetujui' => $disetujui,
                'jumlah_dikirim' => $dikirim,
                'jumlah_diterima' => $diterima,
                'catatan' => null,
            ]);
        }

        $this->stats['permintaan']++;
    }

    private function getStokMinimum(int $obatId, int $fasilitasId): int
    {
        $avg = $this->getAvgConsumption($obatId, $fasilitasId);

        return max(20, (int) ($avg * 1.5));
    }

    private function getAvgConsumption(int $obatId, int $fasilitasId): int
    {
        $faskes = $this->puskesmas->firstWhere('id', $fasilitasId)
            ?? $this->pustu->firstWhere('id', $fasilitasId);

        $tipe = $faskes && $faskes->tipe === 'puskesmas' ? 'puskesmas' : 'pustu';

        [$avgPuskesmas, $avgPustu] = self::KONSUMSI[$obatId] ?? [50, 20];

        $avg = $tipe === 'puskesmas' ? $avgPuskesmas : $avgPustu;

        return (int) ($avg * self::SKALA_KONSUMSI);
    }

    // =======================================================================
    //  4. DISTRIBUSI OBAT
    // =======================================================================

    public function distributeInitialStock(Carbon $date): void
    {
        foreach ($this->puskesmas as $puskesmas) {
            $this->generateDistribusi(null, $puskesmas->id, 'dinas_ke_puskesmas', $date, true);
        }
        foreach ($this->pustu as $pustu) {
            $indukId = $pustu->puskesmas_induk_id;
            if ($indukId) {
                $this->generateDistribusi($indukId, $pustu->id, 'puskesmas_ke_pustu', $date, true);
            }
        }
    }

    public function generateAllDistribusi(Carbon $date): void
    {
        foreach ($this->puskesmas as $puskesmas) {
            $this->generateDistribusi(null, $puskesmas->id, 'dinas_ke_puskesmas', $date, false);
        }

        foreach ($this->pustu as $pustu) {
            $indukId = $pustu->puskesmas_induk_id;
            if (! $indukId) {
                continue;
            }
            $this->generateDistribusi($indukId, $pustu->id, 'puskesmas_ke_pustu', $date, false);
        }
    }

    public function generateDistribusi(?int $pengirimId, int $penerimaId, string $tipe, Carbon $date, bool $isInitial = false): void
    {
        $faskes = $this->puskesmas->firstWhere('id', $penerimaId)
            ?? $this->pustu->firstWhere('id', $penerimaId);

        $obatIds = [];
        $currentFaskesStock = $this->faskesStock[$penerimaId] ?? [];

        // Full sweep: SEMUA obat master dipertimbangkan tiap bulan (tanpa
        // slice acak) agar pemakaian full-coverage selalu punya stok.
        // Ambang pakai rata-rata TERSKALA x3 (~3 bulan konsumsi berjalan).
        foreach ($this->obatList->keys()->all() as $obatId) {
            // Target ikut gelombang musiman agar stok cukup saat puncak.
            $avg = $this->getAvgConsumption($obatId, $penerimaId) * $this->getMusimanMultiplier($date, $obatId);
            $stok = $currentFaskesStock[$obatId] ?? 0;
            if ($stok < $avg * 3 || $isInitial) {
                $obatIds[] = $obatId;
            }
        }

        shuffle($obatIds);
        if (empty($obatIds)) {
            return;
        }

        $qtyMap = [];
        foreach ($obatIds as $obatId) {
            $avgCons = $this->getAvgConsumption($obatId, $penerimaId) * $this->getMusimanMultiplier($date, $obatId);
            $currentStock = $this->faskesStock[$penerimaId][$obatId] ?? 0;
            $qty = max(50, (int) ($avgCons * random_int(15, 30) / 10) - $currentStock);
            $sourceStock = $pengirimId === null ? ($this->gudangStock[$obatId] ?? 0) : ($this->faskesStock[$pengirimId][$obatId] ?? 0);
            $qty = min($qty, $sourceStock);
            if ($qty > 0) {
                $qtyMap[$obatId] = $qty;
            }
        }
        if (empty($qtyMap)) {
            return;
        }

        $adminGudang = $this->users['sys_admin_gudang@mail.com'];
        $adminDinas = $this->users['sys_admin_dinas@mail.com'];
        $tanggalKirim = (clone $date)->addDays(random_int(10, 25));

        $distribusi = DistribusiObat::create([
            'nomor_surat_jalan' => 'SJ/'.$date->format('Y/m').'/'.str_pad((string) $this->counterDistribusi++, 4, '0', STR_PAD_LEFT),
            'permintaan_id' => null,
            'tipe_distribusi' => $tipe,
            'fasilitas_pengirim_id' => $pengirimId,
            'fasilitas_penerima_id' => $penerimaId,
            'status' => 'diterima',
            'tanggal_kirim' => $tanggalKirim,
            'tanggal_terima' => (clone $tanggalKirim)->addDays(random_int(1, 2)),
            'pengirim_id' => $pengirimId ? ($this->users[$pengirimId]?->id ?? $adminGudang->id) : $adminGudang->id,
            'penerima_id' => $this->users[$penerimaId]?->id ?? $adminDinas->id,
            'catatan' => null,
        ]);

        $sumberDanaAlokasi = [];
        $detailCount = 0;
        $detailRows = [];
        $usedBatchIds = [];
        $finalQty = [];
        $now = now()->toDateTimeString();
        // Hoist lookup yang berulang per obat.
        $pengirimUserId = $pengirimId ? ($this->users[$pengirimId]?->id ?? $adminGudang->id) : $adminGudang->id;
        $penerimaUserId = $this->users[$penerimaId]?->id ?? $adminDinas->id;
        $namaPenerima = $this->pustu->firstWhere('id', $penerimaId)?->nama ?? 'Pustu';

        foreach ($qtyMap as $obatId => $qty) {
            $sourceStock = $pengirimId === null ? ($this->gudangStock[$obatId] ?? 0) : ($this->faskesStock[$pengirimId][$obatId] ?? 0);
            $qty = min($qty, $sourceStock);
            if ($qty <= 0) {
                continue;
            }
            $batchId = $pengirimId === null ? $this->deductGudangBatch($obatId, $qty) : $this->deductFacilityBatch($pengirimId, $obatId, $qty);
            $finalBatchId = $batchId ?? 0;
            if ($finalBatchId <= 0) {
                continue;
            }

            if ($pengirimId === null) {
                $stokSebelum = $this->gudangStock[$obatId] ?? 0;
                $this->gudangStock[$obatId] = $stokSebelum - $qty;
                $this->faskesStock[$penerimaId][$obatId] = ($this->faskesStock[$penerimaId][$obatId] ?? 0) + $qty;
                $this->batchByFaskes[$penerimaId][$obatId][] = ['batch_id' => $batchId, 'jumlah' => $qty];
                $this->bufferRiwayat[] = [
                    'fasilitas_id' => null, 'obat_id' => $obatId, 'tipe' => 'distribusi_keluar', 'jumlah' => -$qty,
                    'stok_sebelum' => $stokSebelum, 'stok_sesudah' => $stokSebelum - $qty,
                    'referensi_type' => DistribusiObat::class, 'referensi_id' => $distribusi->id,
                    'user_id' => $adminGudang->id, 'keterangan' => 'Distribusi ke faskes', 'tanggal' => $tanggalKirim->toDateTimeString(),

                ];
            } else {
                $stokSebelum = $this->faskesStock[$pengirimId][$obatId] ?? 0;
                $this->faskesStock[$pengirimId][$obatId] = $stokSebelum - $qty;
                $this->faskesStock[$penerimaId][$obatId] = ($this->faskesStock[$penerimaId][$obatId] ?? 0) + $qty;
                $this->batchByFaskes[$penerimaId][$obatId][] = ['batch_id' => $batchId, 'jumlah' => $qty];
                $this->bufferRiwayat[] = [
                    'fasilitas_id' => $pengirimId, 'obat_id' => $obatId, 'tipe' => 'distribusi_keluar', 'jumlah' => -$qty,
                    'stok_sebelum' => $stokSebelum, 'stok_sesudah' => $stokSebelum - $qty,
                    'referensi_type' => DistribusiObat::class, 'referensi_id' => $distribusi->id,
                    'user_id' => $pengirimUserId,
                    'keterangan' => 'Distribusi ke '.$namaPenerima, 'tanggal' => $tanggalKirim->toDateTimeString(),

                ];
                $this->catatMutasi($pengirimId, $obatId, 'keluar', $qty);
            }

            $stokPenerimaSebelum = ($this->faskesStock[$penerimaId][$obatId] ?? 0) - $qty;
            $this->bufferRiwayat[] = [
                'fasilitas_id' => $penerimaId, 'obat_id' => $obatId, 'tipe' => 'distribusi_masuk', 'jumlah' => $qty,
                'stok_sebelum' => $stokPenerimaSebelum, 'stok_sesudah' => $stokPenerimaSebelum + $qty,
                'referensi_type' => DistribusiObat::class, 'referensi_id' => $distribusi->id,
                'user_id' => $penerimaUserId, 'keterangan' => 'Penerimaan distribusi', 'tanggal' => $tanggalKirim->toDateTimeString(),

            ];
            $this->catatMutasi($penerimaId, $obatId, 'masuk', $qty);

            $detailRows[] = [
                'distribusi_id' => $distribusi->id,
                'obat_id' => $obatId,
                'batch_id' => $finalBatchId,
                'jumlah' => $qty,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $usedBatchIds[$obatId] = $finalBatchId;
            $finalQty[$obatId] = $qty;
            $detailCount++;
        }

        if ($detailCount === 0) {
            $distribusi->delete();

            return;
        }

        DetailDistribusiObat::insert($detailRows);

        // Satu query untuk semua batch (pengganti BatchStok::find per detail).
        $batchMap = BatchStok::whereIn('id', array_values(array_unique($usedBatchIds)))->get()->keyBy('id');
        $batchCopyRows = [];
        foreach ($usedBatchIds as $obatId => $batchId) {
            $batch = $batchMap->get($batchId);
            if (! $batch) {
                continue;
            }
            $qty = $finalQty[$obatId];
            $batchCopyRows[] = [
                'penerimaan_id' => $batch->penerimaan_id,
                'fasilitas_id' => $penerimaId,
                'sumber_dana_id' => $batch->sumber_dana_id,
                'obat_id' => $obatId,
                'batch_number' => $batch->batch_number,
                'tanggal_expired' => $batch->tanggal_expired,
                'jumlah' => $qty,
                'status' => 'tersedia',
                'tanggal_masuk' => $tanggalKirim->toDateTimeString(),
                'harga_beli' => $batch->harga_beli,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($batch->sumber_dana_id) {
                $sdId = $batch->sumber_dana_id;
                $harga = (float) ($batch->harga_beli ?: $this->estimateHarga($obatId));
                $biaya = $harga * $qty;

                if (! isset($sumberDanaAlokasi[$sdId])) {
                    $sumberDanaAlokasi[$sdId] = ['jumlah_obat' => 0, 'total_biaya' => 0.0];
                }
                $sumberDanaAlokasi[$sdId]['jumlah_obat']++;
                $sumberDanaAlokasi[$sdId]['total_biaya'] += $biaya;
            }
        }
        if (! empty($batchCopyRows)) {
            BatchStok::insert($batchCopyRows);
        }

        foreach ($sumberDanaAlokasi as $sdId => $data) {
            SumberDanaPenggunaan::create([
                'sumber_dana_id' => $sdId,
                'rko_id' => null,
                'fasilitas_id' => $penerimaId,
                'tipe' => 'alokasi',
                'jumlah_obat' => $data['jumlah_obat'],
                'total_biaya' => $data['total_biaya'],
                'tanggal' => $tanggalKirim,
                'keterangan' => 'Alokasi distribusi ke '.($faskes->nama ?? 'Unknown').' ('.$tipe.')',
            ]);
        }

        $this->stats['distribusi']++;
    }

    private function deductGudangBatch(int $obatId, int $jumlah): ?int
    {
        $batches = &$this->batchByGudang[$obatId];
        if (empty($batches)) {
            return null;
        }

        usort($batches, fn ($a, $b) => $a['batch_id'] <=> $b['batch_id']);

        $remaining = $jumlah;
        $lastBatchId = null;

        foreach ($batches as &$batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch['jumlah'], $remaining);
            $batch['jumlah'] -= $take;
            $remaining -= $take;
            $lastBatchId = $batch['batch_id'];
        }

        $batches = array_filter($batches, fn ($b) => $b['jumlah'] > 0);
        $batches = array_values($batches);

        return $lastBatchId;
    }

    private function deductFacilityBatch(int $fasilitasId, int $obatId, int $jumlah): ?int
    {
        $batches = &$this->batchByFaskes[$fasilitasId][$obatId];
        if (empty($batches)) {
            return null;
        }

        usort($batches, fn ($a, $b) => $a['batch_id'] <=> $b['batch_id']);

        $remaining = $jumlah;
        $lastBatchId = null;

        foreach ($batches as &$batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch['jumlah'], $remaining);
            $batch['jumlah'] -= $take;
            $remaining -= $take;
            $lastBatchId = $batch['batch_id'];
        }

        $batches = array_values(array_filter($batches, fn ($b) => $b['jumlah'] > 0));

        return $lastBatchId;
    }

    // =======================================================================
    //  5. LPLPO (Monthly Reports)
    // =======================================================================

    public function generateAllLPLPO(Carbon $date): void
    {
        foreach ($this->puskesmas as $puskesmas) {
            $this->generateLPLPO($puskesmas->id, $date);
        }
        foreach ($this->pustu as $pustu) {
            $this->generateLPLPO($pustu->id, $date);
        }
    }

    private function generateLPLPO(int $fasilitasId, Carbon $date): void
    {
        $user = $this->users[$fasilitasId] ?? $this->users['sys_admin_gudang@mail.com'];

        $tglBuat = (clone $date)->endOfMonth();

        if (LaporanLplpo::where('fasilitas_id', $fasilitasId)->where('periode_bulan', $date->month)->where('periode_tahun', $date->year)->exists()) {
            return;
        }
        $lplpo = LaporanLplpo::create([
            'nomor_laporan' => 'LPLPO/'.$date->format('Y/m').'/'.str_pad((string) $this->counterLPLPO++, 4, '0', STR_PAD_LEFT),
            'fasilitas_id' => $fasilitasId,
            'periode_bulan' => $date->month,
            'periode_tahun' => $date->year,
            'status' => 'selesai',
            'tanggal_pembuatan' => $tglBuat,
            'dibuat_oleh' => $user->id,
            'catatan' => null,
        ]);

        $drugsWithMovement = $this->obatList->random(min(random_int(15, 30), $this->obatList->count()));

        foreach ($drugsWithMovement as $obat) {
            $stokAwal = (int) ($this->faskesStockStartOfMonth[$fasilitasId][$obat->id]
                ?? $this->faskesStock[$fasilitasId][$obat->id]
                ?? 0);

            // Dari accumulator in-memory (diisi distribusi/pemakaian bulan ini,
            // dibaca sebelum retur berjalan — sama seperti query SUM sebelumnya).
            $mutasi = $this->mutasiBulan[$fasilitasId][$obat->id] ?? ['masuk' => 0, 'keluar' => 0];
            $masuk = (int) $mutasi['masuk'];
            $keluar = (int) $mutasi['keluar'];

            $sisa = $stokAwal + $masuk - $keluar;
            $permintaanLanjut = max(50, $keluar + random_int(10, 50));
            $stokOptimum = max(50, (int) ($this->getAvgConsumption($obat->id, $fasilitasId) * 3));

            DetailLplpo::create([
                'lplpo_id' => $lplpo->id,
                'obat_id' => $obat->id,
                'stok_awal' => $stokAwal,
                'jumlah_masuk' => $masuk,
                'jumlah_keluar' => $keluar,
                'sisa_stok' => $sisa,
                'stok_optimum' => $stokOptimum,
                'permintaan_selanjutnya' => $permintaanLanjut,
                'sudah_diminta' => random_int(0, 1),
                'permintaan_id' => null,
                'keterangan' => null,
            ]);
        }

        $this->stats['lplpo']++;
    }

    // =======================================================================
    //  6. RKO (Annual Plans — generated in December for next year)
    // =======================================================================

    public function generateAllRKO(Carbon $date): void
    {
        $tahunRko = $date->year + 1;

        foreach ($this->puskesmas as $puskesmas) {
            $this->generateRKO($puskesmas->id, $tahunRko, $date);
        }
    }

    private function generateRKO(int $fasilitasId, int $tahunRko, Carbon $date): void
    {
        $user = $this->users[$fasilitasId] ?? $this->users['sys_admin_gudang@mail.com'];
        $adminDinas = $this->users['sys_admin_dinas@mail.com'];
        $tglBuat = (clone $date)->endOfMonth();

        $tahunIni = $date->year;
        $startYear = Carbon::create($tahunIni, 1, 1, 0, 0, 0);
        $endYear = Carbon::create($tahunIni, 12, 31, 23, 59, 59);

        $obatIds = [];
        foreach (self::KONSUMSI as $obatId => $_) {
            $avg = $this->getAvgConsumption($obatId, $fasilitasId);

            $pemakaianTahunIni = (int) RiwayatStok::query()
                ->where('fasilitas_id', $fasilitasId)
                ->where('obat_id', $obatId)
                ->whereIn('tipe', ['keluar', 'distribusi_keluar', 'rusak', 'expired', 'penyesuaian', 'hilang'])
                ->where('jumlah', '<', 0)
                ->whereBetween('tanggal', [$startYear, $endYear])
                ->sum('jumlah');
            $pemakaianTahunIni = abs($pemakaianTahunIni);

            if ($pemakaianTahunIni > 0 || $avg > 0) {
                $obatIds[] = $obatId;
            }
        }

        if (empty($obatIds)) {
            return;
        }

        shuffle($obatIds);
        $obatIds = array_slice($obatIds, 0, min(count($obatIds), random_int(20, 40)));

        $lplpoReferensi = LaporanLplpo::where('fasilitas_id', $fasilitasId)
            ->where('periode_tahun', $tahunIni)
            ->latest()
            ->first();

        if (LaporanRko::where('fasilitas_id', $fasilitasId)->where('periode_tahun', $tahunRko)->exists()) {
            return;
        }
        $rko = LaporanRko::create([
            'nomor_rko' => 'RKO/'.$date->format('Y/m').'/'.str_pad((string) $this->counterRKO++, 4, '0', STR_PAD_LEFT),
            'fasilitas_id' => $fasilitasId,
            'periode_tahun' => $tahunRko,
            'status' => 'disetujui',
            'tanggal_pembuatan' => $tglBuat,
            'tanggal_pengajuan' => $tglBuat,
            'tanggal_disetujui' => (clone $tglBuat)->addDays(random_int(1, 5)),
            'dibuat_oleh' => $user->id,
            'disetujui_oleh' => $adminDinas->id,
            'catatan' => null,
        ]);

        $totalAnggaran = 0;
        $drugCount = 0;

        foreach ($obatIds as $obatId) {
            $obat = $this->obatList->get($obatId);
            if (! $obat) {
                continue;
            }

            $avg = $this->getAvgConsumption($obatId, $fasilitasId);

            $pemakaianTahunLalu = (int) RiwayatStok::query()
                ->where('fasilitas_id', $fasilitasId)
                ->where('obat_id', $obatId)
                ->whereIn('tipe', ['keluar', 'distribusi_keluar', 'rusak', 'expired', 'penyesuaian', 'hilang'])
                ->where('jumlah', '<', 0)
                ->whereBetween('tanggal', [$startYear, $endYear])
                ->sum('jumlah');
            $pemakaianTahunLalu = abs($pemakaianTahunLalu);

            $bulanExpr = DB::connection()->getDriverName() === 'sqlite'
                ? "COUNT(DISTINCT strftime('%Y-%m', tanggal))"
                : "COUNT(DISTINCT DATE_FORMAT(tanggal, '%Y-%m'))";

            $bulanAktif = (int) DB::table('riwayat_stok')
                ->where('fasilitas_id', $fasilitasId)
                ->where('obat_id', $obatId)
                ->whereIn('tipe', ['keluar', 'distribusi_keluar'])
                ->where('jumlah', '<', 0)
                ->whereBetween('tanggal', [$startYear, $endYear])
                ->selectRaw($bulanExpr.' as cnt')
                ->value('cnt') ?? 0;

            if ($pemakaianTahunLalu > 0 && $bulanAktif > 0) {
                $rataRataBulanan = (int) round($pemakaianTahunLalu / $bulanAktif);
            } else {
                $rataRataBulanan = $avg;
            }

            $stokAkhir = $this->faskesStock[$fasilitasId][$obatId] ?? 0;
            $hargaPerkiraan = (float) ($obat->harga_satuan ?: $this->estimateHarga($obat->id));

            $venKategori = $obat->ven_kategori;
            if (! in_array($venKategori, ['V', 'E', 'N'])) {
                $venKategori = match (true) {
                    in_array($obatId, [26, 42]) => 'V',
                    in_array($obatId, [44, 11, 7, 6, 39, 34, 37, 28, 41]) => 'E',
                    default => 'N',
                };
            }

            $bufferPersen = match ($venKategori) {
                'V' => 30,
                'E' => 20,
                'N' => 10,
                default => 15,
            };

            $kebutuhanTahunan = $rataRataBulanan * 18;
            $rencanaKebutuhan = max(0, $kebutuhanTahunan - $stokAkhir);
            $bufferQty = (int) round($rencanaKebutuhan * $bufferPersen / 100);
            $totalKebutuhan = $rencanaKebutuhan + $bufferQty;
            $usulan = $totalKebutuhan;
            $totalHarga = $usulan * $hargaPerkiraan;
            $totalAnggaran += $totalHarga;
            $drugCount++;

            DetailRko::create([
                'rko_id' => $rko->id,
                'obat_id' => $obatId,
                'pemakaian_tahun_sebelumnya' => (int) $pemakaianTahunLalu,
                'rata_rata_pemakaian_bulanan' => $rataRataBulanan,
                'stok_akhir' => $stokAkhir,
                'kebutuhan_tahunan' => $kebutuhanTahunan,
                'rencana_kebutuhan' => $rencanaKebutuhan,
                'buffer_stock_persen' => $bufferPersen,
                'buffer_stok_qty' => $bufferQty,
                'total_kebutuhan' => $totalKebutuhan,
                'usulan' => $usulan,
                'harga_perkiraan' => $hargaPerkiraan,
                'total_harga' => $totalHarga,
                'ven_kategori' => $venKategori,
                'abc_kategori' => null,
                'lplpo_referensi_id' => $lplpoReferensi?->id,
                'prediksi_id' => null,
                'keterangan' => null,
            ]);
        }

        $rko->update(['total_anggaran' => $totalAnggaran]);

        if ($totalAnggaran > 0 && $drugCount > 0) {
            SumberDanaPenggunaan::create([
                'sumber_dana_id' => $this->pickSumberDana($tahunRko)->id,
                'rko_id' => $rko->id,
                'fasilitas_id' => $fasilitasId,
                'tipe' => 'alokasi',
                'jumlah_obat' => $drugCount,
                'total_biaya' => $totalAnggaran,
                'tanggal' => $tglBuat,
                'keterangan' => 'RKO tahun '.$tahunRko.' untuk '.($this->puskesmas->firstWhere('id', $fasilitasId)?->nama ?? 'Puskesmas'),
            ]);
        }

        $this->stats['rko']++;
    }

    // =======================================================================
    //  7. RETUR OBAT (Every 3 months)
    // =======================================================================

    public function generateAllRetur(Carbon $date): void
    {
        foreach ($this->puskesmas as $puskesmas) {
            if (random_int(1, 100) > 30) {
                continue;
            }
            $this->generateRetur($puskesmas->id, null, 'puskesmas_ke_gudang', $date);
        }
    }

    private function generateRetur(int $pengirimId, ?int $penerimaId, string $tipeRetur, Carbon $date): void
    {
        $alasanList = ['expired', 'rusak', 'kelebihan_stok', 'near_expiry'];
        $alasan = $alasanList[array_rand($alasanList)];

        $tipeRiwayat = match ($alasan) {
            'rusak' => 'rusak',
            'expired' => 'expired',
            default => 'penyesuaian',
        };

        $isReturnToGudang = in_array($alasan, ['expired', 'kelebihan_stok', 'near_expiry'], true);

        $adminDinas = $this->users['sys_admin_dinas@mail.com'];
        $petugas = $this->users[$pengirimId] ?? $adminDinas;

        $faskes = $this->puskesmas->firstWhere('id', $pengirimId)
            ?? $this->pustu->firstWhere('id', $pengirimId);

        $tglRetur = (clone $date)->addDays(random_int(1, 15));

        $retur = ReturObat::create([
            'nomor_retur' => ReturObat::generateNomorRetur($pengirimId, $tglRetur->format('Y-m-d')),
            'distribusi_id' => null,
            'fasilitas_pengirim_id' => $pengirimId,
            'fasilitas_penerima_id' => $penerimaId,
            'tipe_retur' => $tipeRetur,
            'alasan' => $alasan,
            'alasan_lainnya' => null,
            'status' => 'selesai',
            'tanggal_retur' => $tglRetur,
            'tanggal_disetujui' => (clone $date)->addDays(random_int(2, 5)),
            'tanggal_ditolak' => null,
            'tanggal_dikirim' => (clone $date)->addDays(random_int(3, 7)),
            'tanggal_diterima' => (clone $date)->addDays(random_int(5, 10)),
            'disetujui_oleh' => $adminDinas->id,
            'catatan' => 'Retur '.str_replace('_', ' ', $alasan),
        ]);

        $returnCount = random_int(1, 3);
        $selectedObat = $this->obatList->random(min($returnCount, $this->obatList->count()));

        foreach ($selectedObat as $obat) {
            $currentStock = $this->faskesStock[$pengirimId][$obat->id] ?? 0;

            $maxQty = match ($alasan) {
                'expired' => min(50, $currentStock),
                'rusak' => min(20, $currentStock),
                'kelebihan_stok' => min(100, $currentStock),
                'near_expiry' => min(50, $currentStock),
                default => min(30, $currentStock),
            };
            $minQty = match ($alasan) {
                'rusak' => 2,
                default => 5,
            };

            if ($maxQty < $minQty) {
                continue;
            }

            $qty = random_int($minQty, $maxQty);

            if ($qty <= 0) {
                continue;
            }

            $this->faskesStock[$pengirimId][$obat->id] = $currentStock - $qty;

            $batchId = $this->deductFacilityBatch($pengirimId, $obat->id, $qty);

            DetailReturObat::create([
                'retur_id' => $retur->id,
                'obat_id' => $obat->id,
                'batch_id' => $batchId ?? 0,
                'jumlah_retur' => $qty,
                'bukti_foto' => null,
                'catatan' => 'Retur '.str_replace('_', ' ', $alasan),
            ]);

            $this->bufferRiwayat[] = [
                'fasilitas_id' => $pengirimId,
                'obat_id' => $obat->id,
                'tipe' => $tipeRiwayat,
                'jumlah' => -$qty,
                'stok_sebelum' => $currentStock,
                'stok_sesudah' => $currentStock - $qty,
                'referensi_type' => ReturObat::class,
                'referensi_id' => $retur->id,
                'user_id' => $petugas->id,
                'keterangan' => 'Retur: '.str_replace('_', ' ', $alasan),
                'tanggal' => $retur->tanggal_retur,
            ];

            if ($isReturnToGudang && $qty > 0) {
                $stokSebelumGudang = $this->gudangStock[$obat->id] ?? 0;
                $this->gudangStock[$obat->id] = $stokSebelumGudang + $qty;

                $sumberDanaRetur = $batchId
                    ? optional(BatchStok::find($batchId))->sumber_dana_id
                    : null;

                BatchStok::create([
                    'penerimaan_id' => null,
                    'fasilitas_id' => null,
                    'sumber_dana_id' => $sumberDanaRetur,
                    'obat_id' => $obat->id,
                    'batch_number' => 'RTN/'.$date->format('Y/m').'/'.strtoupper(substr($obat->kode_obat ?? '', -4)).'/'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT),
                    'tanggal_expired' => now()->addMonths(random_int(6, 12)),
                    'jumlah' => $qty,
                    'status' => 'tersedia',
                    'tanggal_masuk' => $retur->tanggal_retur,
                    'harga_beli' => 0,
                ]);

                $this->bufferRiwayat[] = [
                    'fasilitas_id' => null,
                    'obat_id' => $obat->id,
                    'tipe' => 'masuk',
                    'jumlah' => $qty,
                    'stok_sebelum' => $stokSebelumGudang,
                    'stok_sesudah' => $stokSebelumGudang + $qty,
                    'referensi_type' => ReturObat::class,
                    'referensi_id' => $retur->id,
                    'user_id' => $adminDinas->id,
                    'keterangan' => 'Retur masuk dari '.($faskes->nama ?? 'faskes').' ('.str_replace('_', ' ', $alasan).')',
                    'tanggal' => $retur->tanggal_retur,
                ];
            }
        }

        $this->stats['retur']++;
    }

    // =======================================================================
    //  FINAL PERSIST
    // =======================================================================

    public function persistFinalStock(): void
    {
        foreach ($this->gudangStock as $obatId => $jumlah) {
            StokGudang::updateOrCreate(
                ['obat_id' => $obatId],
                ['jumlah' => max(0, $jumlah)],
            );
        }

        foreach ($this->faskesStock as $fasilitasId => $stocks) {
            foreach ($stocks as $obatId => $jumlah) {
                if ($jumlah < 0) {
                    $jumlah = 0;
                }
                // Always persist, even if 0, to keep recalculate consistency
                // But skip if never existed and 0 (to avoid mass insert)
                if ($jumlah == 0 && ! StokFaskes::where('fasilitas_id', $fasilitasId)->where('obat_id', $obatId)->exists()) {
                    continue;
                }

                StokFaskes::updateOrCreate(
                    [
                        'fasilitas_id' => $fasilitasId,
                        'obat_id' => $obatId,
                    ],
                    [
                        'jumlah' => max(0, $jumlah),
                        'stok_minimum' => $this->getStokMinimum($obatId, $fasilitasId),
                    ],
                );
            }
        }
    }
}
