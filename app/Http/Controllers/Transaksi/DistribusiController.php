<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\DistribusiRequest;
use App\Models\DetailDistribusiObat;
use App\Models\DistribusiObat;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\PenerimaanStok;
use App\Models\PermintaanObat;
use App\Models\User;
use App\Services\FefoService;
use App\Services\NotificationService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DistribusiController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', DistribusiObat::class);

        $user = $request->user();
        $filters = $request->only(['search', 'tipe', 'status', 'fasilitas', 'dari', 'sampai']);

        $query = DistribusiObat::query()->with([
            'fasilitasPengirim:id,nama', 'fasilitasPenerima:id,nama',
            'pengirim:id,name', 'permintaan:id,nomor_permintaan',
        ])->withCount('details');

        $this->scopeList($query, $user);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_surat_jalan', 'like', "%{$v}%"));
        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe_distribusi', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['fasilitas'] ?? null, fn ($q, $v) => $q
            ->where('fasilitas_pengirim_id', $v)
            ->orWhere('fasilitas_penerima_id', $v));
        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_kirim', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_kirim', '<=', $v));

        $query->latest('tanggal_kirim')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('transaksi.distribusi.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'faskesList' => FasilitasKesehatan::query()->orderBy('nama')->get(['id', 'nama', 'tipe']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DistribusiObat::class);

        $user = $request->user();
        $lockedPengirim = filled($user->fasilitas_kesehatan_id);

        $permintaan = $request->query('permintaan_id')
            ? PermintaanObat::with('details')->find($request->query('permintaan_id'))
            : null;

        $defaultTipe = $lockedPengirim ? 'puskesmas_ke_pustu' : 'dinas_ke_puskesmas';

        return view('transaksi.distribusi.form', [
            'distribusi' => null,
            'lockedPengirim' => $lockedPengirim,
            'defaultPengirim' => $user->fasilitas_kesehatan_id,
            'defaultTipe' => old('tipe_distribusi', $defaultTipe),
            'defaultPenerima' => old('fasilitas_penerima_id', $permintaan?->fasilitas_pengirim_id),
            'permintaan' => $permintaan,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama', 'tipe']),
            'oldRows' => $permintaan?->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'jumlah' => $d->jumlah_disetujui ?? $d->jumlah_diminta,
            ])->toArray() ?? [],
        ]);
    }

    public function store(DistribusiRequest $request, FefoService $fefo): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $data['_pengirim_id'] = $this->resolvePengirim($request, $data);

        $distribusi = DB::transaction(function () use ($data, $user, $fefo) {
            $distribusi = DistribusiObat::create([
                'tipe_distribusi' => $data['tipe_distribusi'],
                'fasilitas_pengirim_id' => $data['_pengirim_id'],
                'fasilitas_penerima_id' => $data['fasilitas_penerima_id'],
                'permintaan_id' => $data['permintaan_id'] ?? null,
                'status' => 'draft',
                'tanggal_kirim' => $data['tanggal_kirim'] ?? null,
                'pengirim_id' => $user->id,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->syncDetails($distribusi, $data['rows'], $fefo);

            return $distribusi;
        });

        return redirect()->route('distribusi.show', $distribusi)->with('success', "Distribusi {$distribusi->nomor_surat_jalan} berhasil dibuat (draft).");
    }

    public function show(DistribusiObat $distribusi): View
    {
        $this->authorize('view', $distribusi);

        $distribusi->loadMissing([
            'fasilitasPengirim:id,nama', 'fasilitasPenerima:id,nama,tipe',
            'pengirim:id,name', 'penerima:id,name', 'permintaan:id,nomor_permintaan,status',
            'penerimaanStok:id,nomor_penerimaan,status',
            'details.obat:id,kode_obat,nama_obat,satuan', 'details.batch:id,batch_number,tanggal_expired',
        ]);

        return view('transaksi.distribusi.show', [
            'distribusi' => $distribusi,
        ]);
    }

    public function edit(DistribusiObat $distribusi): View
    {
        $this->authorize('update', $distribusi);

        $user = auth()->user();

        return view('transaksi.distribusi.form', [
            'distribusi' => $distribusi,
            'lockedPengirim' => filled($user->fasilitas_kesehatan_id),
            'defaultPengirim' => $distribusi->fasilitas_pengirim_id,
            'defaultTipe' => old('tipe_distribusi', $distribusi->tipe_distribusi),
            'defaultPenerima' => old('fasilitas_penerima_id', $distribusi->fasilitas_penerima_id),
            'permintaan' => $distribusi->permintaan,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama', 'tipe']),
            'oldRows' => $distribusi->details()->with('obat:id,kode_obat,nama_obat')->get()->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'jumlah' => $d->jumlah,
            ])->toArray(),
        ]);
    }

    public function update(DistribusiRequest $request, DistribusiObat $distribusi, FefoService $fefo): RedirectResponse
    {
        $data = $request->validated();
        $data['_pengirim_id'] = $this->resolvePengirim($request, $data);

        DB::transaction(function () use ($distribusi, $data, $fefo): void {
            $distribusi->update([
                'tipe_distribusi' => $data['tipe_distribusi'],
                'fasilitas_pengirim_id' => $data['_pengirim_id'],
                'fasilitas_penerima_id' => $data['fasilitas_penerima_id'],
                'permintaan_id' => $data['permintaan_id'] ?? $distribusi->permintaan_id,
                'tanggal_kirim' => $data['tanggal_kirim'] ?? $distribusi->tanggal_kirim,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->syncDetails($distribusi, $data['rows'], $fefo);
        });

        return redirect()->route('distribusi.show', $distribusi)->with('success', 'Distribusi berhasil diperbarui.');
    }

    public function destroy(DistribusiObat $distribusi): RedirectResponse
    {
        $this->authorize('delete', $distribusi);

        $distribusi->details()->delete();
        $distribusi->delete();

        return redirect()->route('distribusi.index')->with('success', 'Distribusi draft berhasil dihapus.');
    }

    public function kirim(DistribusiObat $distribusi, NotificationService $notifikasi): RedirectResponse
    {
        $this->authorize('update', $distribusi);

        if ($distribusi->status !== 'draft') {
            return redirect()->route('distribusi.show', $distribusi)
                ->with('error', 'Hanya distribusi draft yang dapat dikirim.');
        }

        $distribusi->update([
            'status' => 'dalam_pengiriman',
            'tanggal_kirim' => $distribusi->tanggal_kirim ?? now()->toDateString(),
        ]);

        $distribusi->permintaan?->update(['status' => 'sedang_didistribusi']);

        $notifikasi->notifyFaskesUsers(
            $distribusi->fasilitas_penerima_id,
            'Distribusi dalam pengiriman',
            "Surat jalan {$distribusi->nomor_surat_jalan} sedang dikirim ke faskes Anda.",
            route('distribusi.show', $distribusi),
        );

        return redirect()->route('distribusi.show', $distribusi)->with('success', 'Distribusi dikirim.');
    }

    public function batalkanPengiriman(DistribusiObat $distribusi): RedirectResponse
    {
        $this->authorize('update', $distribusi);

        if ($distribusi->status !== 'dalam_pengiriman') {
            return redirect()->route('distribusi.show', $distribusi)
                ->with('error', 'Hanya pengiriman berjalan yang dapat dibatalkan.');
        }

        $distribusi->update(['status' => 'draft']);

        if ($distribusi->permintaan?->status === 'sedang_didistribusi') {
            $distribusi->permintaan->update(['status' => 'disetujui']);
        }

        return redirect()->route('distribusi.show', $distribusi)->with('success', 'Pengiriman dibatalkan, kembali ke draft.');
    }

    public function tolak(Request $request, DistribusiObat $distribusi): RedirectResponse
    {
        $this->authorize('update', $distribusi);

        if (! in_array($distribusi->status, ['draft', 'dalam_pengiriman'], true)) {
            return redirect()->route('distribusi.show', $distribusi)
                ->with('error', 'Distribusi tidak dapat ditolak pada status ini.');
        }

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $distribusi->update([
            'status' => 'ditolak',
            'tanggal_ditolak' => now()->toDateString(),
            'catatan' => trim(($distribusi->catatan ? $distribusi->catatan."\n" : '').'Ditolak: '.$data['alasan']),
        ]);

        if ($distribusi->permintaan?->status === 'sedang_didistribusi') {
            $distribusi->permintaan->update(['status' => 'disetujui']);
        }

        return redirect()->route('distribusi.show', $distribusi)->with('success', 'Distribusi ditolak.');
    }

    /**
     * Buat draft penerimaan tipe distribusi yang tertaut (untuk penerima).
     */
    public function buatPenerimaan(DistribusiObat $distribusi, NotificationService $notifikasi): RedirectResponse
    {
        $this->authorize('view', $distribusi);

        if ($distribusi->status !== 'dalam_pengiriman') {
            return redirect()->route('distribusi.show', $distribusi)
                ->with('error', 'Penerimaan hanya bisa dibuat untuk distribusi dalam pengiriman.');
        }

        if ($distribusi->penerimaanStok) {
            return redirect()->route('penerimaan.show', $distribusi->penerimaanStok)
                ->with('info', 'Penerimaan untuk distribusi ini sudah ada.');
        }

        $penerimaan = PenerimaanStok::create([
            'tipe' => 'distribusi',
            'fasilitas_id' => $distribusi->fasilitas_penerima_id,
            'tanggal_penerimaan' => now()->toDateString(),
            'user_id' => auth()->id(),
            'status' => 'draft',
            'distribusi_id' => $distribusi->id,
            'catatan' => 'Dari distribusi '.$distribusi->nomor_surat_jalan,
            'total_biaya' => 0,
        ]);

        foreach ($distribusi->details()->with('batch')->get() as $detail) {
            $penerimaan->details()->create([
                'obat_id' => $detail->obat_id,
                'batch_number' => $detail->batch?->batch_number,
                'tanggal_expired' => $detail->batch?->tanggal_expired,
                'jumlah' => $detail->jumlah,
            ]);
        }

        $notifikasi->notifyRole(
            'admin_gudang',
            'Penerimaan distribusi dibuat',
            "Penerimaan {$penerimaan->nomor_penerimaan} menunggu konfirmasi.",
            route('penerimaan.show', $penerimaan),
        );

        return redirect()->route('penerimaan.show', $penerimaan)->with('success', 'Draft penerimaan dibuat. Konfirmasi untuk menambah stok.');
    }

    /**
     * Batasi daftar sesuai aturan DistribusiObatPolicy::view.
     */
    private function scopeList($query, User $user): void
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin_dinas')) {
            return;
        }

        $faskesId = $user->fasilitas_kesehatan_id;

        if ($user->hasRole('admin_gudang') && blank($faskesId)) {
            return;
        }

        if (blank($faskesId)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($faskesId): void {
            $q->where('fasilitas_pengirim_id', $faskesId)
                ->orWhere('fasilitas_penerima_id', $faskesId);
        });
    }

    private function resolvePengirim(Request $request, array $data): ?int
    {
        if (filled($request->user()->fasilitas_kesehatan_id)) {
            return (int) $request->user()->fasilitas_kesehatan_id;
        }

        return $data['fasilitas_pengirim_id'] ?? null;
    }

    /**
     * Ganti seluruh detail dengan alokasi ulang server-side (FEFO/FIFO/LIFO).
     *
     * @param  array<int, array{obat_id: int, jumlah: int}>  $rows
     */
    private function syncDetails(DistribusiObat $distribusi, array $rows, FefoService $fefo): void
    {
        $distribusi->details()->delete();

        foreach ($rows as $row) {
            $obat = Obat::findOrFail($row['obat_id']);
            $metode = $obat->metode_stok->value ?? 'fefo';
            $alokasi = $fefo->allocate((int) $row['obat_id'], (int) $row['jumlah'], $distribusi->fasilitas_pengirim_id, $metode);

            $teralokasi = collect($alokasi)->sum('jumlah');

            if ($teralokasi < (int) $row['jumlah']) {
                abort(422, "Stok {$obat->nama_obat} tidak mencukupi (tersedia {$teralokasi}, diminta {$row['jumlah']}).");
            }

            foreach ($alokasi as $item) {
                DetailDistribusiObat::create([
                    'distribusi_id' => $distribusi->id,
                    'obat_id' => $row['obat_id'],
                    'batch_id' => $item['batch_id'],
                    'jumlah' => $item['jumlah'],
                ]);
            }
        }
    }
}
