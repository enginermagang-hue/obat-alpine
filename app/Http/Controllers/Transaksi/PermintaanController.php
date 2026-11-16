<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermintaanRequest;
use App\Models\FasilitasKesehatan;
use App\Models\PermintaanObat;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PermintaanController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', PermintaanObat::class);

        $user = $request->user();
        $filters = $request->only(['search', 'tipe', 'status', 'fasilitas', 'dari', 'sampai']);

        $query = PermintaanObat::query()->with([
            'fasilitasPengirim:id,nama,tipe', 'fasilitasTujuan:id,nama', 'disetujuiOleh:id,name',
        ])->withCount('details');

        $this->scopeList($query, $user);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_permintaan', 'like', "%{$v}%"));
        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe_permintaan', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['fasilitas'] ?? null, fn ($q, $v) => $q
            ->where('fasilitas_pengirim_id', $v)
            ->orWhere('fasilitas_tujuan_id', $v));
        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_permintaan', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_permintaan', '<=', $v));

        $query->latest('tanggal_permintaan')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('transaksi.permintaan.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'faskesList' => $this->faskesOptions($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PermintaanObat::class);
        abort_if($request->user()->hasRole('super_admin'), 403);

        $user = $request->user();
        $faskes = $user->fasilitasKesehatan;

        $tipe = $faskes?->tipe === 'pustu' ? 'pustu_ke_puskesmas' : 'puskesmas_ke_dinas';
        $tujuan = $faskes?->tipe === 'pustu' ? $faskes->puskesmas_induk_id : null;
        $tujuanNama = $faskes?->tipe === 'pustu'
            ? FasilitasKesehatan::whereKey($tujuan)->value('nama')
            : 'Gudang Dinas Kesehatan';

        return view('transaksi.permintaan.form', [
            'permintaan' => null,
            'tipe' => $tipe,
            'tujuanId' => $tujuan,
            'tujuanNama' => $tujuanNama,
            'pengirimNama' => $faskes?->nama,
            'oldRows' => [],
        ]);
    }

    public function store(PermintaanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        abort_if($request->user()->hasRole('super_admin'), 403);

        $user = $request->user();
        $faskes = $user->fasilitasKesehatan;

        $tipe = $faskes?->tipe === 'pustu' ? 'pustu_ke_puskesmas' : 'puskesmas_ke_dinas';

        $permintaan = PermintaanObat::create([
            'fasilitas_pengirim_id' => $user->fasilitas_kesehatan_id,
            'fasilitas_tujuan_id' => $faskes?->tipe === 'pustu' ? $faskes->puskesmas_induk_id : null,
            'tipe_permintaan' => $tipe,
            'status' => 'draft',
            'tanggal_permintaan' => $data['tanggal_permintaan'],
            'catatan' => $data['catatan'] ?? null,
            'surat_permintaan' => $data['surat_permintaan'] ?? null,
        ]);

        $this->syncDetails($permintaan, $data['rows']);

        return redirect()->route('permintaan.show', $permintaan)->with('success', "Permintaan {$permintaan->nomor_permintaan} berhasil dibuat (draft).");
    }

    public function show(PermintaanObat $permintaan): View
    {
        $this->authorize('view', $permintaan);

        $permintaan->loadMissing([
            'fasilitasPengirim:id,nama,tipe', 'fasilitasTujuan:id,nama',
            'disetujuiOleh:id,name', 'details.obat:id,kode_obat,nama_obat,satuan',
            'distribusi:id,permintaan_id,nomor_surat_jalan,status',
        ]);

        return view('transaksi.permintaan.show', [
            'permintaan' => $permintaan,
            'bisaSetujui' => $this->bisaProses($permintaan),
        ]);
    }

    public function edit(PermintaanObat $permintaan): View
    {
        $this->authorize('update', $permintaan);

        $permintaan->loadMissing('details');

        return view('transaksi.permintaan.form', [
            'permintaan' => $permintaan,
            'tipe' => $permintaan->tipe_permintaan,
            'tujuanId' => $permintaan->fasilitas_tujuan_id,
            'tujuanNama' => $permintaan->fasilitasTujuan?->nama ?? 'Gudang Dinas Kesehatan',
            'pengirimNama' => $permintaan->fasilitasPengirim?->nama,
            'oldRows' => $permintaan->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'jumlah_diminta' => $d->jumlah_diminta,
                'catatan' => $d->catatan,
            ])->toArray(),
        ]);
    }

    public function update(PermintaanRequest $request, PermintaanObat $permintaan): RedirectResponse
    {
        $data = $request->validated();

        $permintaan->update([
            'tanggal_permintaan' => $data['tanggal_permintaan'],
            'catatan' => $data['catatan'] ?? null,
            'surat_permintaan' => $data['surat_permintaan'] ?? null,
        ]);

        $this->syncDetails($permintaan, $data['rows']);

        return redirect()->route('permintaan.show', $permintaan)->with('success', 'Permintaan berhasil diperbarui.');
    }

    public function destroy(PermintaanObat $permintaan): RedirectResponse
    {
        $this->authorize('delete', $permintaan);

        $permintaan->details()->delete();
        $permintaan->delete();

        return redirect()->route('permintaan.index')->with('success', 'Permintaan draft berhasil dihapus.');
    }

    public function submit(PermintaanObat $permintaan, NotificationService $notifikasi): RedirectResponse
    {
        $this->authorize('update', $permintaan);

        if ($permintaan->status !== 'draft') {
            return redirect()->route('permintaan.show', $permintaan)
                ->with('error', 'Hanya permintaan draft yang dapat diajukan.');
        }

        $permintaan->update(['status' => 'menunggu_persetujuan']);

        $notifikasi->notifyPermintaanApprovers(
            $permintaan->fresh(),
            'Permintaan baru menunggu persetujuan',
            "Permintaan {$permintaan->nomor_permintaan} dari {$permintaan->fasilitasPengirim?->nama}.",
            route('permintaan.show', $permintaan),
        );

        return redirect()->route('permintaan.show', $permintaan)->with('success', 'Permintaan diajukan. Menunggu persetujuan.');
    }

    public function approve(Request $request, PermintaanObat $permintaan): RedirectResponse
    {
        $this->authorize('update', $permintaan);

        if ($permintaan->status !== 'menunggu_persetujuan' || ! $this->bisaProses($permintaan)) {
            return redirect()->route('permintaan.show', $permintaan)
                ->with('error', 'Permintaan tidak dapat disetujui pada status ini.');
        }

        $data = $request->validate([
            'jumlah_disetujui' => ['nullable', 'array'],
            'jumlah_disetujui.*' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($permintaan->details as $detail) {
            $setuju = (int) ($data['jumlah_disetujui'][$detail->id] ?? $detail->jumlah_diminta);
            $detail->update(['jumlah_disetujui' => max(0, $setuju)]);
        }

        $permintaan->update([
            'status' => 'disetujui',
            'tanggal_disetujui' => now()->toDateString(),
            'disetujui_oleh' => $request->user()->id,
        ]);

        return redirect()->route('permintaan.show', $permintaan)->with('success', 'Permintaan disetujui.');
    }

    public function reject(Request $request, PermintaanObat $permintaan): RedirectResponse
    {
        $this->authorize('update', $permintaan);

        if ($permintaan->status !== 'menunggu_persetujuan' || ! $this->bisaProses($permintaan)) {
            return redirect()->route('permintaan.show', $permintaan)
                ->with('error', 'Permintaan tidak dapat ditolak pada status ini.');
        }

        $data = $request->validate([
            'alasan_penolakan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $permintaan->update([
            'status' => 'ditolak',
            'tanggal_ditolak' => now()->toDateString(),
            'alasan_penolakan' => $data['alasan_penolakan'],
        ]);

        return redirect()->route('permintaan.show', $permintaan)->with('success', 'Permintaan ditolak.');
    }

    /**
     * Apakah user login boleh memproses (setujui/tolak) permintaan ini.
     * Tujuan untuk pustu→puskesmas; dinas (tanpa faskes) untuk puskesmas→dinas.
     */
    private function bisaProses(PermintaanObat $permintaan): bool
    {
        $user = auth()->user();

        if (! $user?->hasPermissionTo('update_permintaan_obat')) {
            return false;
        }

        if ($permintaan->tipe_permintaan === 'pustu_ke_puskesmas') {
            return (int) $permintaan->fasilitas_tujuan_id === (int) $user->fasilitas_kesehatan_id;
        }

        return blank($user->fasilitas_kesehatan_id);
    }

    /**
     * Batasi daftar sesuai aturan PermintaanObatPolicy::view.
     */
    private function scopeList($query, User $user): void
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin_dinas') || $user->hasRole('admin_gudang')) {
            $query->where('tipe_permintaan', 'puskesmas_ke_dinas');

            return;
        }

        $faskesId = $user->fasilitas_kesehatan_id;

        if (blank($faskesId)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $faskes = $user->fasilitasKesehatan;

        if ($faskes?->tipe === 'puskesmas') {
            $pustuIds = $faskes->pustu()->pluck('fasilitas_kesehatan.id')->toArray();

            $query->where(function ($q) use ($faskesId, $pustuIds): void {
                $q->where(function ($q) use ($pustuIds): void {
                    $q->where('tipe_permintaan', 'pustu_ke_puskesmas')
                        ->whereIn('fasilitas_pengirim_id', $pustuIds);
                })->orWhere(function ($q) use ($faskesId): void {
                    $q->where('tipe_permintaan', 'puskesmas_ke_dinas')
                        ->where('fasilitas_pengirim_id', $faskesId);
                });
            });

            return;
        }

        $query->where('tipe_permintaan', 'pustu_ke_puskesmas')
            ->where('fasilitas_pengirim_id', $faskesId);
    }

    /**
     * @return Collection<int, FasilitasKesehatan>
     */
    private function faskesOptions(User $user)
    {
        if (blank($user->fasilitas_kesehatan_id)) {
            return FasilitasKesehatan::query()->orderBy('nama')->get(['id', 'nama']);
        }

        return FasilitasKesehatan::whereIn('id', FaskesAccess::visibleFasilitasIds($user))
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncDetails(PermintaanObat $permintaan, array $rows): void
    {
        $permintaan->details()->delete();

        foreach ($rows as $row) {
            $permintaan->details()->create([
                'obat_id' => $row['obat_id'],
                'jumlah_diminta' => (int) $row['jumlah_diminta'],
                'catatan' => $row['catatan'] ?? null,
            ]);
        }
    }
}
