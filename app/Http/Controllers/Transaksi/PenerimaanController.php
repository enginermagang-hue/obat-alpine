<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PenerimaanRequest;
use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PenerimaanStok;
use App\Models\RiwayatStok;
use App\Models\SumberDana;
use App\Models\Supplier;
use App\Services\NotificationService;
use App\Services\StokService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PenerimaanController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', PenerimaanStok::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'tipe', 'status', 'fasilitas', 'supplier', 'dari', 'sampai']);

        $query = PenerimaanStok::query()->with([
            'fasilitas:id,nama', 'supplier:id,nama', 'sumberDana:id,nama', 'user:id,name',
        ]);

        if ($lockedFaskes) {
            $query->where('fasilitas_id', $user->fasilitas_kesehatan_id);
        } elseif (($filters['fasilitas'] ?? null) === 'gudang') {
            $query->whereNull('fasilitas_id');
        } else {
            $query->when($filters['fasilitas'] ?? null, fn ($q, $v) => $q->where('fasilitas_id', $v));
        }

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_penerimaan', 'like', "%{$v}%"));
        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['supplier'] ?? null, fn ($q, $v) => $q->where('supplier_id', $v));
        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_penerimaan', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_penerimaan', '<=', $v));

        $query->latest('tanggal_penerimaan')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('transaksi.penerimaan.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'lockedFaskes' => $lockedFaskes,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'supplierList' => Supplier::query()->where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']),
            'kpi' => $this->kpi($user, $lockedFaskes),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PenerimaanStok::class);

        $user = $request->user();

        return view('transaksi.penerimaan.form', [
            'penerimaan' => null,
            'lockedFaskes' => filled($user->fasilitas_kesehatan_id),
            'defaultFaskes' => $user->fasilitas_kesehatan_id,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'supplierList' => Supplier::query()->where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']),
            'danaList' => SumberDana::query()->where('status', 'aktif')->orderByDesc('tahun')->orderBy('kode')->get(['id', 'kode', 'nama', 'tahun']),
            'oldRows' => [],
        ]);
    }

    public function store(PenerimaanRequest $request, StokService $stokService): RedirectResponse
    {
        $data = $request->validated();

        $penerimaan = PenerimaanStok::create([
            'tipe' => $data['tipe'],
            'fasilitas_id' => $data['fasilitas_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'sumber_dana_id' => $data['sumber_dana_id'] ?? null,
            'nomor_po' => $data['nomor_po'] ?? null,
            'nomor_invoice' => $data['nomor_invoice'] ?? null,
            'tanggal_penerimaan' => $data['tanggal_penerimaan'],
            'user_id' => $request->user()->id,
            'status' => 'draft',
            'catatan' => $data['catatan'] ?? null,
            'total_biaya' => 0,
        ]);

        $this->syncDetails($penerimaan, $data['rows']);

        return redirect()->route('penerimaan.show', $penerimaan)->with('success', "Penerimaan {$penerimaan->nomor_penerimaan} berhasil dibuat (draft).");
    }

    public function show(PenerimaanStok $penerimaan): View
    {
        $this->authorize('view', $penerimaan);

        $penerimaan->loadMissing([
            'fasilitas:id,nama', 'supplier:id,nama', 'sumberDana:id,nama',
            'user:id,name', 'details.obat:id,kode_obat,nama_obat,satuan',
        ]);

        $riwayat = RiwayatStok::query()
            ->with(['obat:id,nama_obat'])
            ->where('referensi_type', PenerimaanStok::class)
            ->where('referensi_id', $penerimaan->id)
            ->latest('id')
            ->limit(30)
            ->get();

        return view('transaksi.penerimaan.show', [
            'penerimaan' => $penerimaan,
            'riwayat' => $riwayat,
        ]);
    }

    public function edit(PenerimaanStok $penerimaan): View
    {
        $this->authorize('update', $penerimaan);

        $penerimaan->loadMissing('details');

        return view('transaksi.penerimaan.form', [
            'penerimaan' => $penerimaan,
            'lockedFaskes' => filled(auth()->user()->fasilitas_kesehatan_id),
            'defaultFaskes' => $penerimaan->fasilitas_id,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), auth()->user())->get(['id', 'nama']),
            'supplierList' => Supplier::query()->orderBy('nama')->get(['id', 'nama']),
            'danaList' => SumberDana::query()->orderByDesc('tahun')->orderBy('kode')->get(['id', 'kode', 'nama', 'tahun']),
            'oldRows' => $penerimaan->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'batch_number' => $d->batch_number,
                'tanggal_expired' => $d->tanggal_expired?->format('Y-m-d'),
                'jumlah' => $d->jumlah,
                'harga_satuan' => (string) ($d->harga_satuan ?? ''),
                'keterangan' => $d->keterangan,
            ])->toArray(),
        ]);
    }

    public function update(PenerimaanRequest $request, PenerimaanStok $penerimaan): RedirectResponse
    {
        $data = $request->validated();

        $penerimaan->update([
            'tipe' => $data['tipe'],
            'fasilitas_id' => $data['fasilitas_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'sumber_dana_id' => $data['sumber_dana_id'] ?? null,
            'nomor_po' => $data['nomor_po'] ?? null,
            'nomor_invoice' => $data['nomor_invoice'] ?? null,
            'tanggal_penerimaan' => $data['tanggal_penerimaan'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        $this->syncDetails($penerimaan, $data['rows']);

        return redirect()->route('penerimaan.show', $penerimaan)->with('success', 'Penerimaan berhasil diperbarui.');
    }

    public function destroy(PenerimaanStok $penerimaan): RedirectResponse
    {
        $this->authorize('delete', $penerimaan);

        $penerimaan->details()->delete();
        $penerimaan->delete();

        return redirect()->route('penerimaan.index')->with('success', 'Penerimaan draft berhasil dihapus.');
    }

    public function confirm(PenerimaanStok $penerimaan, StokService $stokService, NotificationService $notifikasi): RedirectResponse
    {
        $this->authorize('confirm', $penerimaan);

        if ($penerimaan->status !== 'draft') {
            return redirect()->route('penerimaan.show', $penerimaan)
                ->with('error', 'Hanya penerimaan draft yang dapat dikonfirmasi.');
        }

        $stokService->prosesPenerimaan($penerimaan->loadMissing('details'));
        $penerimaan->update(['status' => 'dikonfirmasi']);

        $notifikasi->notifyRole(
            'admin_gudang',
            'Penerimaan dikonfirmasi',
            "Penerimaan {$penerimaan->nomor_penerimaan} telah dikonfirmasi.",
            route('penerimaan.show', $penerimaan),
        );

        return redirect()->route('penerimaan.show', $penerimaan)->with('success', 'Penerimaan dikonfirmasi. Stok bertambah.');
    }

    public function cancel(PenerimaanStok $penerimaan, StokService $stokService): RedirectResponse
    {
        $this->authorize('cancel', $penerimaan);

        if ($penerimaan->status !== 'dikonfirmasi') {
            return redirect()->route('penerimaan.show', $penerimaan)
                ->with('error', 'Hanya penerimaan yang sudah dikonfirmasi yang dapat dibatalkan.');
        }

        $stokService->reversePenerimaan($penerimaan->loadMissing('details'), $penerimaan->details);
        $penerimaan->update(['status' => 'dibatalkan']);

        return redirect()->route('penerimaan.show', $penerimaan)->with('success', 'Penerimaan dibatalkan. Stok dikembalikan.');
    }

    /**
     * Ganti seluruh detail. Total biaya dihitung ulang di server.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncDetails(PenerimaanStok $penerimaan, array $rows): void
    {
        $penerimaan->details()->delete();

        $total = 0;
        foreach ($rows as $row) {
            $jumlah = (int) $row['jumlah'];
            $harga = isset($row['harga_satuan']) && $row['harga_satuan'] !== '' ? (float) $row['harga_satuan'] : null;
            $subtotal = $harga !== null ? $jumlah * $harga : null;
            $total += $subtotal ?? 0;

            $penerimaan->details()->create([
                'obat_id' => $row['obat_id'],
                'batch_number' => $row['batch_number'],
                'tanggal_expired' => $row['tanggal_expired'],
                'jumlah' => $jumlah,
                'harga_satuan' => $harga,
                'sub_total' => $subtotal,
                'keterangan' => $row['keterangan'] ?? null,
            ]);
        }

        $penerimaan->update(['total_biaya' => $total]);
    }

    /**
     * @return array<string, int|float>
     */
    private function kpi(\App\Models\User $user, bool $lockedFaskes): array
    {
        $scoped = fn () => PenerimaanStok::query()
            ->when($lockedFaskes, fn ($q) => $q->where('fasilitas_id', $user->fasilitas_kesehatan_id));

        return [
            'draft' => $scoped()->where('status', 'draft')->count(),
            'bulan_ini' => $scoped()->whereBetween('tanggal_penerimaan', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
            'nilai' => (float) $scoped()->where('status', 'dikonfirmasi')
                ->whereBetween('tanggal_penerimaan', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('total_biaya'),
        ];
    }
}
