<?php

namespace App\Http\Controllers\Stok;

use App\Helpers\BatchNumberGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpnameRequest;
use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\OpnameStok;
use App\Models\StokFaskes;
use App\Models\StokGudang;
use App\Services\StokService;
use App\Support\FaskesAccess;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpnameController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', OpnameStok::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'tipe', 'status', 'fasilitas', 'dari', 'sampai']);

        $query = OpnameStok::query()->with(['fasilitas:id,nama', 'user:id,name'])->withCount('details');

        FaskesAccess::scopeQuery($query, $user);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_opname', 'like', "%{$v}%"));
        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        if (! $lockedFaskes) {
            $query->when($filters['fasilitas'] ?? null, fn ($q, $v) => $q->where('fasilitas_id', $v));
        }

        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_opname', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_opname', '<=', $v));

        $query->latest('tanggal_opname')->latest('id');

        $perPage = (int) ($user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        return view('stok.opname.index', [
            'opnames' => $query->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'lockedFaskes' => $lockedFaskes,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', OpnameStok::class);

        $user = $request->user();

        return view('stok.opname.form', [
            'opname' => null,
            'lockedFaskes' => filled($user->fasilitas_kesehatan_id),
            'defaultFaskes' => $user->fasilitas_kesehatan_id,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'oldRows' => [],
        ]);
    }

    public function store(OpnameRequest $request, StokService $stokService): RedirectResponse
    {
        $data = $request->validated();

        $opname = OpnameStok::create([
            'tipe' => $data['tipe'],
            'fasilitas_id' => $data['fasilitas_id'] ?? null,
            'tanggal_opname' => $data['tanggal_opname'],
            'status' => 'draft',
            'user_id' => $request->user()->id,
            'catatan' => $data['catatan'] ?? null,
        ]);

        $this->syncDetails($opname, $data);

        if (($data['status'] ?? 'draft') !== 'draft') {
            $this->applyStatus($opname->fresh('details'), $data['status'], $stokService);
        }

        return redirect()->route('opname.show', $opname)->with('success', "Opname {$opname->nomor_opname} berhasil dibuat.");
    }

    public function show(OpnameStok $opname): View
    {
        $this->authorize('view', $opname);

        $opname->loadMissing(['fasilitas:id,nama,tipe', 'user:id,name', 'details.obat:id,kode_obat,nama_obat,satuan', 'details.batch:id,batch_number']);

        $riwayat = \App\Models\RiwayatStok::query()
            ->with(['user:id,name', 'obat:id,nama_obat', 'fasilitas:id,nama'])
            ->where('referensi_type', OpnameStok::class)
            ->where('referensi_id', $opname->id)
            ->latest('id')
            ->limit(20)
            ->get();

        return view('stok.opname.show', [
            'opname' => $opname,
            'riwayat' => $riwayat,
        ]);
    }

    public function edit(Request $request, OpnameStok $opname): View
    {
        $this->authorize('update', $opname);

        if ($opname->status === 'selesai') {
            return redirect()->route('opname.show', $opname)
                ->with('error', 'Opname selesai terkunci. Gunakan tombol buka kembali untuk mengubah.');
        }

        $user = $request->user();
        $opname->loadMissing('details');

        return view('stok.opname.form', [
            'opname' => $opname,
            'lockedFaskes' => filled($user->fasilitas_kesehatan_id),
            'defaultFaskes' => $opname->fasilitas_id,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'oldRows' => $opname->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'batch_id' => $d->batch_id,
                'batch_number' => $d->batch_number,
                'tanggal_expired' => $d->tanggal_expired?->format('Y-m-d'),
                'stok_sistem' => $d->stok_sistem,
                'stok_fisik' => $d->stok_fisik,
            ])->toArray(),
        ]);
    }

    public function update(OpnameRequest $request, OpnameStok $opname, StokService $stokService): RedirectResponse
    {
        if ($opname->status === 'selesai') {
            return redirect()->route('opname.show', $opname)
                ->with('error', 'Opname selesai terkunci. Gunakan tombol buka kembali untuk mengubah.');
        }

        $data = $request->validated();

        $this->assertTransisi($opname->status, $data['status'], $opname);

        $opname->update([
            'tipe' => $data['tipe'],
            'fasilitas_id' => $data['fasilitas_id'] ?? null,
            'tanggal_opname' => $data['tanggal_opname'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        $this->syncDetails($opname, $data);

        if ($data['status'] !== $opname->getOriginal('status')) {
            $this->applyStatus($opname->fresh('details'), $data['status'], $stokService);
        }

        return redirect()->route('opname.show', $opname)->with('success', 'Opname berhasil diperbarui.');
    }

    public function destroy(OpnameStok $opname): RedirectResponse
    {
        $this->authorize('delete', $opname);

        if ($opname->status !== 'draft') {
            return redirect()->route('opname.show', $opname)
                ->with('error', 'Hanya opname draft yang dapat dihapus.');
        }

        $opname->details()->delete();
        $opname->delete();

        return redirect()->route('opname.index')->with('success', 'Opname berhasil dihapus.');
    }

    /**
     * Kembalikan opname selesai ke draft (reverse efek stok).
     */
    public function reopen(OpnameStok $opname, StokService $stokService): RedirectResponse
    {
        $this->authorize('update', $opname);

        if ($opname->status !== 'selesai') {
            return redirect()->route('opname.show', $opname)
                ->with('error', 'Hanya opname selesai yang dapat dibuka kembali.');
        }

        $stokService->reverseOpname($opname->loadMissing('details'), $opname->details);
        $opname->update(['status' => 'draft']);

        return redirect()->route('opname.show', $opname)->with('success', 'Opname dibuka kembali ke draft. Efek stok dibatalkan.');
    }

    /**
     * Terapkan perubahan status draft/proses → selesai (efek stok).
     */
    private function applyStatus(OpnameStok $opname, string $status, StokService $stokService): void
    {
        $this->assertTransisi($opname->getOriginal('status') ?? $opname->status, $status, $opname);

        if ($status === 'selesai') {
            $stokService->prosesOpnameSelesai($opname);
        }

        if ($opname->status !== $status) {
            $opname->update(['status' => $status]);
        }
    }

    /**
     * Transisi yang diizinkan: draft → proses/selesai, proses → selesai/draft.
     */
    private function assertTransisi(string $dari, string $ke, OpnameStok $opname): void
    {
        $boleh = [
            'draft' => ['draft', 'proses', 'selesai'],
            'proses' => ['proses', 'selesai', 'draft'],
        ];

        if (! in_array($ke, $boleh[$dari] ?? [], true)) {
            abort(422, "Transisi status {$dari} → {$ke} tidak diizinkan.");
        }

        if ($dari === 'selesai') {
            abort(422, 'Opname selesai terkunci. Buka kembali terlebih dahulu.');
        }
    }

    /**
     * Ganti seluruh detail. Selisih selalu dihitung ulang di server.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncDetails(OpnameStok $opname, array $data): void
    {
        $opname->details()->delete();

        foreach ($data['rows'] as $row) {
            $item = $this->buildDetail($opname, $row);
            $opname->details()->create($item);
        }
    }

    /**
     * Bangun satu baris detail dengan stok sistem & selisih versi server.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildDetail(OpnameStok $opname, array $row): array
    {
        $fisik = (int) $row['stok_fisik'];

        if ($opname->tipe === 'penyesuaian' && ! empty($row['batch_id'])) {
            $batch = BatchStok::query()
                ->whereKey($row['batch_id'])
                ->where('obat_id', $row['obat_id'])
                ->when(
                    $opname->fasilitas_id,
                    fn ($q) => $q->where('fasilitas_id', $opname->fasilitas_id),
                    fn ($q) => $q->whereNull('fasilitas_id'),
                )
                ->firstOrFail();

            return [
                'obat_id' => $row['obat_id'],
                'batch_id' => $batch->id,
                'stok_sistem' => $batch->jumlah,
                'stok_fisik' => $fisik,
                'selisih' => $fisik - $batch->jumlah,
                'batch_number' => $batch->batch_number,
                'tanggal_expired' => $batch->tanggal_expired,
                'keterangan' => $row['keterangan'] ?? null,
            ];
        }

        if ($opname->tipe === 'penyesuaian') {
            $sistem = $opname->fasilitas_id
                ? (int) StokFaskes::where('fasilitas_id', $opname->fasilitas_id)->where('obat_id', $row['obat_id'])->value('jumlah')
                : (int) StokGudang::where('obat_id', $row['obat_id'])->value('jumlah');

            return [
                'obat_id' => $row['obat_id'],
                'batch_id' => null,
                'stok_sistem' => $sistem,
                'stok_fisik' => $fisik,
                'selisih' => $fisik - $sistem,
                'batch_number' => $row['batch_number'] ?? null,
                'tanggal_expired' => $row['tanggal_expired'] ?? null,
                'keterangan' => $row['keterangan'] ?? null,
            ];
        }

        return [
            'obat_id' => $row['obat_id'],
            'batch_id' => null,
            'stok_sistem' => 0,
            'stok_fisik' => $fisik,
            'selisih' => $fisik,
            'batch_number' => $row['batch_number'] ?: BatchNumberGenerator::generate((int) $row['obat_id']),
            'tanggal_expired' => $row['tanggal_expired'] ?? null,
            'keterangan' => $row['keterangan'] ?? null,
        ];
    }
}
