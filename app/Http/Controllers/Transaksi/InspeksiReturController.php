<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\InspeksiRequest;
use App\Models\BatchStok;
use App\Models\DetailReturObat;
use App\Models\InspeksiRetur;
use App\Models\ReturObat;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InspeksiReturController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', InspeksiRetur::class);

        $user = $request->user();
        $filters = $request->only(['search', 'hasil', 'tindakan', 'dari', 'sampai']);

        $query = InspeksiRetur::query()->with([
            'retur:id,nomor_retur,status', 'detailRetur.obat:id,nama_obat',
            'batch:id,batch_number', 'inspector:id,name',
        ]);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q
            ->whereHas('retur', fn ($q) => $q->where('nomor_retur', 'like', "%{$v}%"))
            ->orWhereHas('batch', fn ($q) => $q->where('batch_number', 'like', "%{$v}%")));

        $query->when($filters['hasil'] ?? null, fn ($q, $v) => $q->where('hasil_inspeksi', $v));
        $query->when($filters['tindakan'] ?? null, fn ($q, $v) => $q->where('tindakan', $v));
        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_inspeksi', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_inspeksi', '<=', $v));

        $query->latest('tanggal_inspeksi')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('transaksi.inspeksi.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', InspeksiRetur::class);

        $returId = $request->query('retur_id') ?? old('retur_id');
        $retur = $returId
            ? ReturObat::with('details.obat:id,nama_obat', 'details.batch:id,batch_number')->find($returId)
            : null;

        return view('transaksi.inspeksi.form', [
            'inspeksi' => null,
            'retur' => $retur,
            'returList' => $this->returTerbuka(),
            'oldDetail' => null,
        ]);
    }

    public function store(InspeksiRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->assertKonsisten($data);

        if (InspeksiRetur::where('detail_retur_id', $data['detail_retur_id'])->exists()) {
            return redirect()->back()->withInput()
                ->with('error', 'Item retur ini sudah diinspeksi.');
        }

        InspeksiRetur::create([
            ...$data,
            'inspected_by' => $request->user()->id,
        ]);

        return redirect()->route('inspeksi-retur.index')->with('success', 'Hasil inspeksi berhasil dicatat (tidak mengubah stok).');
    }

    public function show(InspeksiRetur $inspeksiRetur): View
    {
        $this->authorize('view', $inspeksiRetur);

        $inspeksiRetur->loadMissing([
            'retur:id,nomor_retur,status', 'detailRetur.obat:id,nama_obat',
            'batch:id,batch_number,tanggal_expired', 'inspector:id,name',
        ]);

        return view('transaksi.inspeksi.show', [
            'inspeksi' => $inspeksiRetur,
        ]);
    }

    public function edit(InspeksiRetur $inspeksiRetur): View
    {
        $this->authorize('update', $inspeksiRetur);

        $inspeksiRetur->loadMissing(['retur', 'detailRetur.obat:id,nama_obat', 'batch:id,batch_number']);

        return view('transaksi.inspeksi.form', [
            'inspeksi' => $inspeksiRetur,
            'retur' => $inspeksiRetur->retur()->with('details.obat:id,nama_obat', 'details.batch:id,batch_number')->first(),
            'returList' => $this->returTerbuka(),
            'oldDetail' => old('detail_retur_id', $inspeksiRetur->detail_retur_id),
            'oldBatch' => old('batch_id', $inspeksiRetur->batch_id),
        ]);
    }

    public function update(InspeksiRequest $request, InspeksiRetur $inspeksiRetur): RedirectResponse
    {
        $data = $request->validated();

        $this->assertKonsisten($data, $inspeksiRetur->id);

        $inspeksiRetur->update($data);

        return redirect()->route('inspeksi-retur.show', $inspeksiRetur)->with('success', 'Hasil inspeksi berhasil diperbarui (tidak mengubah stok).');
    }

    /**
     * Retur yang bisa diinspeksi: sudah diterima/selesai dan masih punya item belum diinspeksi.
     *
     * @return Collection<int, ReturObat>
     */
    private function returTerbuka()
    {
        return ReturObat::query()
            ->whereIn('status', ['diterima', 'selesai'])
            ->orderByDesc('tanggal_retur')
            ->limit(200)
            ->get(['id', 'nomor_retur', 'status']);
    }

    /**
     * Pastikan detail & batch memang milik retur yang dipilih.
     */
    private function assertKonsisten(array $data, ?int $kecualiId = null): void
    {
        $detail = DetailReturObat::whereKey($data['detail_retur_id'])
            ->where('retur_id', $data['retur_id'])
            ->first();

        if (! $detail) {
            abort(422, 'Item retur tidak termasuk dalam retur yang dipilih.');
        }

        $batch = BatchStok::whereKey($data['batch_id'])
            ->where('obat_id', $detail->obat_id)
            ->first();

        if (! $batch) {
            abort(422, 'Batch tidak sesuai dengan obat pada item retur.');
        }

        $duplikat = InspeksiRetur::where('detail_retur_id', $data['detail_retur_id']);

        if ($kecualiId) {
            $duplikat->where('id', '!=', $kecualiId);
        }

        if ($duplikat->exists()) {
            abort(422, 'Item retur ini sudah diinspeksi.');
        }
    }
}
