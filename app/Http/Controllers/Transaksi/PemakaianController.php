<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PemakaianRequest;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PemakaianObat;
use App\Models\RiwayatStok;
use App\Services\FefoService;
use App\Services\StokService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PemakaianController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', PemakaianObat::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'layanan', 'fasilitas', 'obat', 'dari', 'sampai']);

        $query = PemakaianObat::query()->with([
            'fasilitas:id,nama', 'user:id,name',
        ])->withCount('details');

        FaskesAccess::scopeQuery($query, $user, 'fasilitas_id');

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q
            ->where('nama_pasien', 'like', "%{$v}%")
            ->orWhere('no_rekam_medis', 'like', "%{$v}%")
            ->orWhere('nomor_pemakaian', 'like', "%{$v}%"));

        $query->when($filters['layanan'] ?? null, fn ($q, $v) => $q->where('jenis_pelayanan', $v));

        if (! $lockedFaskes) {
            $query->when($filters['fasilitas'] ?? null, fn ($q, $v) => $q->where('fasilitas_id', $v));
        }

        $query->when($filters['obat'] ?? null, fn ($q, $v) => $q->whereHas('details', fn ($q) => $q->where('obat_id', $v)));
        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_pemakaian', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_pemakaian', '<=', $v));

        $query->latest('tanggal_pemakaian')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('transaksi.pemakaian.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'lockedFaskes' => $lockedFaskes,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'layananList' => ['rawat_jalan' => 'Rawat Jalan', 'rawat_inap' => 'Rawat Inap', 'uks' => 'UKS', 'posyandu' => 'Posyandu', 'pusling' => 'Pusling', 'gigi' => 'Poli Gigi', 'laboratorium' => 'Laboratorium', 'apotek' => 'Apotek', 'lainnya' => 'Lainnya'],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PemakaianObat::class);

        $user = $request->user();

        return view('transaksi.pemakaian.form', [
            'pemakaian' => null,
            'defaultFaskes' => $user->fasilitas_kesehatan_id,
            'faskesNama' => $user->fasilitasKesehatan?->nama,
            'oldRows' => [],
        ]);
    }

    public function store(PemakaianRequest $request, StokService $stokService, FefoService $fefo): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $pemakaian = DB::transaction(function () use ($data, $user, $fefo, $stokService) {
            $pemakaian = PemakaianObat::create([
                'fasilitas_id' => $user->fasilitas_kesehatan_id,
                'tanggal_pemakaian' => $data['tanggal_pemakaian'],
                'jenis_pelayanan' => $data['jenis_pelayanan'],
                'nama_pasien' => $data['nama_pasien'] ?? null,
                'no_rekam_medis' => $data['no_rekam_medis'] ?? null,
                'diagnosa_kode' => $data['diagnosa_kode'] ?? null,
                'user_id' => $user->id,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->syncDetails($pemakaian, $data['rows'], $fefo);
            $stokService->prosesPemakaian($pemakaian->fresh('details'));

            return $pemakaian;
        });

        return redirect()->route('pemakaian.show', $pemakaian)->with('success', "Pemakaian {$pemakaian->nomor_pemakaian} berhasil dicatat. Stok berkurang.");
    }

    public function show(PemakaianObat $pemakaian): View
    {
        $this->authorize('view', $pemakaian);

        $pemakaian->loadMissing([
            'fasilitas:id,nama', 'user:id,name',
            'details.obat:id,kode_obat,nama_obat,satuan', 'details.batch:id,batch_number,tanggal_expired',
        ]);

        $riwayat = RiwayatStok::query()
            ->with('obat:id,nama_obat')
            ->where('referensi_type', \App\Models\DetailPemakaianObat::class)
            ->whereIn('referensi_id', $pemakaian->details->pluck('id'))
            ->latest('id')
            ->limit(30)
            ->get();

        return view('transaksi.pemakaian.show', [
            'pemakaian' => $pemakaian,
            'riwayat' => $riwayat,
        ]);
    }

    public function edit(PemakaianObat $pemakaian): View
    {
        $this->authorize('update', $pemakaian);

        $pemakaian->loadMissing('details');

        return view('transaksi.pemakaian.form', [
            'pemakaian' => $pemakaian,
            'defaultFaskes' => $pemakaian->fasilitas_id,
            'faskesNama' => $pemakaian->fasilitas?->nama,
            'oldRows' => $pemakaian->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'jumlah' => $d->jumlah,
                'dosis' => $d->dosis,
                'satuan_dosis' => $d->satuan_dosis,
                'keterangan' => $d->catatan,
            ])->toArray(),
        ]);
    }

    public function update(PemakaianRequest $request, PemakaianObat $pemakaian, StokService $stokService, FefoService $fefo): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($pemakaian, $data, $fefo, $stokService): void {
            $stokService->reversePemakaian($pemakaian->loadMissing('details'));

            $pemakaian->update([
                'tanggal_pemakaian' => $data['tanggal_pemakaian'],
                'jenis_pelayanan' => $data['jenis_pelayanan'],
                'nama_pasien' => $data['nama_pasien'] ?? null,
                'no_rekam_medis' => $data['no_rekam_medis'] ?? null,
                'diagnosa_kode' => $data['diagnosa_kode'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->syncDetails($pemakaian, $data['rows'], $fefo);
            $stokService->prosesPemakaian($pemakaian->fresh('details'));
        });

        return redirect()->route('pemakaian.show', $pemakaian)->with('success', 'Pemakaian berhasil diperbarui.');
    }

    public function destroy(PemakaianObat $pemakaian, StokService $stokService): RedirectResponse
    {
        $this->authorize('delete', $pemakaian);

        $stokService->reversePemakaian($pemakaian->loadMissing('details'));
        $pemakaian->details()->delete();
        $pemakaian->delete();

        return redirect()->route('pemakaian.index')->with('success', 'Pemakaian berhasil dihapus. Stok dikembalikan.');
    }

    /**
     * Ganti seluruh detail dengan alokasi batch server-side (FEFO/FIFO/LIFO).
     *
     * @param  array<int, array{obat_id: int, jumlah: int}>  $rows
     */
    private function syncDetails(PemakaianObat $pemakaian, array $rows, FefoService $fefo): void
    {
        $pemakaian->details()->delete();

        foreach ($rows as $row) {
            $obat = Obat::findOrFail($row['obat_id']);
            $metode = $obat->metode_stok->value ?? 'fefo';
            $alokasi = $fefo->allocate((int) $row['obat_id'], (int) $row['jumlah'], $pemakaian->fasilitas_id, $metode);

            $teralokasi = collect($alokasi)->sum('jumlah');

            if ($teralokasi < (int) $row['jumlah']) {
                abort(422, "Stok {$obat->nama_obat} tidak mencukupi (tersedia {$teralokasi}, diminta {$row['jumlah']}).");
            }

            foreach ($alokasi as $item) {
                $pemakaian->details()->create([
                    'obat_id' => $row['obat_id'],
                    'batch_id' => $item['batch_id'],
                    'jumlah' => $item['jumlah'],
                    'dosis' => $row['dosis'] ?? null,
                    'satuan_dosis' => $row['satuan_dosis'] ?? null,
                    'catatan' => $row['keterangan'] ?? null,
                ]);
            }
        }
    }
}
