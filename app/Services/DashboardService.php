<?php

namespace App\Services;

use App\Models\BatchStok;
use App\Models\DistribusiObat;
use App\Models\FasilitasKesehatan;
use App\Models\ModelPrediksi;
use App\Models\Obat;
use App\Models\PemakaianObat;
use App\Models\PermintaanObat;
use App\Models\StokFaskes;
use App\Models\StokGudang;
use App\Models\User;
use App\Support\FaskesAccess;
use Carbon\Carbon;

/**
 * Agregasi dashboard per role. Semua hitungan di SQL, tanpa loop per faskes.
 */
class DashboardService
{
    /** @return array<string,mixed> */
    public function dinas(): array
    {
        $pending = PermintaanObat::where('status', 'menunggu_persetujuan')->count();
        $kritis = StokFaskes::where('stok_minimum', '>', 0)->whereColumn('jumlah', '<', 'stok_minimum')->count();
        $batch90 = BatchStok::where('jumlah', '>', 0)->whereDate('tanggal_expired', '<=', Carbon::today()->addDays(90))->count();
        $nilaiGudang = (float) StokGudang::join('obat', 'obat.id', '=', 'stok_gudang.obat_id')
            ->selectRaw('SUM(stok_gudang.jumlah * COALESCE(obat.harga_satuan, 0)) as v')->value('v');

        $kritisTop = StokFaskes::with(['obat:id,nama_obat,satuan', 'fasilitas:id,nama'])
            ->where('stok_minimum', '>', 0)->whereColumn('jumlah', '<', 'stok_minimum')
            ->orderBy('jumlah')->limit(5)->get();

        $perFaskes = FasilitasKesehatan::where('tipe', 'puskesmas')->where('status', 'aktif')
            ->orderBy('nama')->get(['id', 'nama'])->map(function ($f) {
                $fId = $f->id;
                $kritisF = StokFaskes::where('fasilitas_id', $fId)->where('stok_minimum', '>', 0)->whereColumn('jumlah', '<', 'stok_minimum')->count();
                $expired = (float) BatchStok::where('fasilitas_id', $fId)->where('jumlah', '>', 0)->whereDate('tanggal_expired', '<', now())->sum('jumlah');
                $pemakaian = PemakaianObat::where('fasilitas_id', $fId)
                    ->whereBetween('tanggal_pemakaian', [now()->startOfMonth(), now()->endOfMonth()])->count();

                return ['id' => $fId, 'nama' => $f->nama, 'kritis' => $kritisF, 'expired' => (int) $expired, 'pemakaian' => $pemakaian];
            })->all();

        $tren = [];
        $labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = now()->startOfMonth()->subMonths($i);
            $labels[] = $d->translatedFormat('M Y');
            $tren[] = (int) DistribusiObat::whereBetween('tanggal_distribusi', [$d->copy()->startOfMonth(), $d->copy()->endOfMonth()])->count();
        }

        return [
            'kpi' => [
                'obat' => Obat::where('status', 'aktif')->count(),
                'nilai_gudang' => $nilaiGudang,
                'batch90' => $batch90,
                'pending' => $pending,
                'kritis' => $kritis,
            ],
            'kritisTop' => $kritisTop,
            'perFaskes' => $perFaskes,
            'tren' => ['type' => 'bar', 'data' => ['labels' => $labels, 'datasets' => [['label' => 'Distribusi/bulan', 'data' => $tren]]]],
        ];
    }

    /** @return array<string,mixed> */
    public function faskes(User $user): array
    {
        $ids = FaskesAccess::visibleFasilitasIds($user);
        $own = $user->fasilitas_kesehatan_id;

        $base = StokFaskes::query()->when(! empty($ids), fn ($q) => $q->whereIn('fasilitas_id', $ids));
        $kritis = (clone $base)->where('stok_minimum', '>', 0)->whereColumn('jumlah', '<', 'stok_minimum')->count();
        $aman = (clone $base)->where(fn ($q) => $q->where('stok_minimum', '<=', 0)->orWhereColumn('jumlah', '>=', 'stok_minimum'))->count();
        $pemakaian = PemakaianObat::when($own, fn ($q) => $q->where('fasilitas_id', $own))
            ->whereBetween('tanggal_pemakaian', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $berjalan = PermintaanObat::when($own, fn ($q) => $q->where('fasilitas_pengirim_id', $own))
            ->whereIn('status', ['draft', 'menunggu_persetujuan', 'disetujui'])->count();

        $batchExp = BatchStok::when(! empty($ids), fn ($q) => $q->whereIn('fasilitas_id', $ids))
            ->with(['obat:id,nama_obat,satuan'])->where('jumlah', '>', 0)
            ->whereDate('tanggal_expired', '<=', Carbon::today()->addDays(90))
            ->orderBy('tanggal_expired')->limit(5)->get();

        $pemakaianTerakhir = PemakaianObat::with('user:id,name')->when($own, fn ($q) => $q->where('fasilitas_id', $own))
            ->latest('tanggal_pemakaian')->limit(5)->get();

        $permintaanBerjalan = PermintaanObat::when($own, fn ($q) => $q->where('fasilitas_pengirim_id', $own))
            ->latest('tanggal_permintaan')->limit(5)->get(['id', 'nomor_permintaan', 'status', 'tanggal_permintaan']);

        $service = new PrediksiRekomendasiService(
            fasilitasId: count($ids) === 1 ? $ids[0] : null,
            visibleFasilitasIds: count($ids) === 1 ? null : $ids,
        );
        $akurasi = (float) ModelPrediksi::query()->where('status', 'aktif')->whereNotNull('akurasi_r2')
            ->when(! empty($ids), fn ($q) => $q->whereIn('fasilitas_id', $ids))->avg('akurasi_r2') ?? 0.0;

        return [
            'kpi' => ['kritis' => $kritis, 'aman' => $aman, 'pemakaian' => $pemakaian, 'berjalan' => $berjalan],
            'batchExp' => $batchExp,
            'pemakaianTerakhir' => $pemakaianTerakhir,
            'permintaanBerjalan' => $permintaanBerjalan,
            'prediksi' => ['top' => $service->lonjakan(3), 'akurasi' => $akurasi],
        ];
    }
}
