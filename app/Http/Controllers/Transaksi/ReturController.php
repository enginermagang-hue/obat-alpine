<?php

namespace App\Http\Controllers\Transaksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReturRequest;
use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\ReturObat;
use App\Models\RiwayatStok;
use App\Models\Supplier;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\StokService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReturController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', ReturObat::class);

        $user = $request->user();
        $filters = $request->only(['search', 'tipe', 'status', 'alasan', 'fasilitas', 'dari', 'sampai']);

        $query = ReturObat::query()->with([
            'fasilitasPengirim:id,nama', 'fasilitasPenerima:id,nama',
            'supplier:id,nama', 'disetujuiOleh:id,name',
        ]);

        $this->scopeList($query, $user);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_retur', 'like', "%{$v}%"));
        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe_retur', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['alasan'] ?? null, fn ($q, $v) => $q->where('alasan', $v));

        $query->when($filters['fasilitas'] ?? null, fn ($q, $v) => $q
            ->where('fasilitas_pengirim_id', $v)
            ->orWhere('fasilitas_penerima_id', $v));

        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_retur', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal_retur', '<=', $v));

        $query->latest('tanggal_retur')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('transaksi.retur.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'faskesList' => FasilitasKesehatan::query()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ReturObat::class);

        $user = $request->user();

        return view('transaksi.retur.form', [
            'retur' => null,
            'lockedPengirim' => filled($user->fasilitas_kesehatan_id),
            'defaultPengirim' => $user->fasilitas_kesehatan_id,
            'defaultTipe' => $this->defaultTipe($user->fasilitas_kesehatan_id),
            'defaultPenerima' => null,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama', 'tipe']),
            'supplierList' => Supplier::query()->where('status', 'aktif')->orderBy('nama')->get(['id', 'nama']),
            'oldRows' => [],
        ]);
    }

    public function store(ReturRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $this->assertTipeBoleh($data['tipe_retur'], $user->fasilitas_kesehatan_id, $data);
        $data['rows'] = $this->simpanBukti($request, $data['rows']);

        [$pengirim, $penerima] = $this->resolvePihak($data['tipe_retur'], $user->fasilitas_kesehatan_id, $data['fasilitas_penerima_id'] ?? null);

        $retur = DB::transaction(function () use ($data, $pengirim, $penerima) {
            $retur = ReturObat::create([
                'tipe_retur' => $data['tipe_retur'],
                'fasilitas_pengirim_id' => $pengirim,
                'fasilitas_penerima_id' => $penerima,
                'supplier_id' => $data['tipe_retur'] === 'gudang_ke_supplier' ? ($data['supplier_id'] ?? null) : null,
                'alasan' => $data['alasan'],
                'alasan_lainnya' => $data['alasan'] === 'lainnya' ? ($data['alasan_lainnya'] ?? null) : null,
                'status' => 'draft',
                'tanggal_retur' => $data['tanggal_retur'],
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->syncDetails($retur, $data['rows']);

            return $retur;
        });

        return redirect()->route('retur.show', $retur)->with('success', "Retur {$retur->nomor_retur} berhasil dibuat (draft).");
    }

    public function show(ReturObat $retur): View
    {
        $this->authorize('view', $retur);

        $retur->loadMissing([
            'fasilitasPengirim:id,nama,tipe', 'fasilitasPenerima:id,nama',
            'supplier:id,nama', 'disetujuiOleh:id,name', 'penerimaan:id,nomor_penerimaan,status',
            'details.obat:id,kode_obat,nama_obat,satuan', 'details.batch:id,batch_number,tanggal_expired',
            'details.inspeksi',
        ]);

        $riwayat = RiwayatStok::query()
            ->with('obat:id,nama_obat')
            ->where('referensi_type', ReturObat::class)
            ->where('referensi_id', $retur->id)
            ->latest('id')
            ->limit(30)
            ->get();

        return view('transaksi.retur.show', [
            'retur' => $retur,
            'riwayat' => $riwayat,
        ]);
    }

    public function edit(ReturObat $retur): View
    {
        $this->authorize('update', $retur);

        $user = auth()->user();
        $retur->loadMissing('details');

        return view('transaksi.retur.form', [
            'retur' => $retur,
            'lockedPengirim' => filled($user->fasilitas_kesehatan_id),
            'defaultPengirim' => $retur->fasilitas_pengirim_id,
            'defaultTipe' => old('tipe_retur', $retur->tipe_retur),
            'defaultPenerima' => old('fasilitas_penerima_id', $retur->fasilitas_penerima_id),
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama', 'tipe']),
            'supplierList' => Supplier::query()->orderBy('nama')->get(['id', 'nama']),
            'oldRows' => $retur->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'batch_id' => $d->batch_id,
                'batch_number' => $d->batch?->batch_number,
                'tanggal_expired' => $d->batch?->tanggal_expired?->format('Y-m-d'),
                'jumlah_retur' => $d->jumlah_retur,
                'keterangan' => $d->catatan,
            ])->toArray(),
        ]);
    }

    public function update(ReturRequest $request, ReturObat $retur): RedirectResponse
    {
        $this->authorize('update', $retur);

        $data = $request->validated();
        $this->assertTipeBoleh($data['tipe_retur'], $request->user()->fasilitas_kesehatan_id, $data);
        $data['rows'] = $this->simpanBukti($request, $data['rows']);

        [$pengirim, $penerima] = $this->resolvePihak($data['tipe_retur'], $request->user()->fasilitas_kesehatan_id, $data['fasilitas_penerima_id'] ?? null);

        DB::transaction(function () use ($retur, $data, $pengirim, $penerima): void {
            $retur->update([
                'tipe_retur' => $data['tipe_retur'],
                'fasilitas_pengirim_id' => $pengirim,
                'fasilitas_penerima_id' => $penerima,
                'supplier_id' => $data['tipe_retur'] === 'gudang_ke_supplier' ? ($data['supplier_id'] ?? null) : null,
                'alasan' => $data['alasan'],
                'alasan_lainnya' => $data['alasan'] === 'lainnya' ? ($data['alasan_lainnya'] ?? null) : null,
                'tanggal_retur' => $data['tanggal_retur'],
                'catatan' => $data['catatan'] ?? null,
            ]);

            $this->syncDetails($retur, $data['rows']);
        });

        return redirect()->route('retur.show', $retur)->with('success', 'Retur berhasil diperbarui.');
    }

    public function destroy(ReturObat $retur): RedirectResponse
    {
        $this->authorize('delete', $retur);

        $retur->details()->delete();
        $retur->delete();

        return redirect()->route('retur.index')->with('success', 'Retur draft berhasil dihapus.');
    }

    public function submit(ReturObat $retur, NotificationService $notifikasi): RedirectResponse
    {
        $this->authorize('submit', $retur);

        if ($retur->status !== 'draft') {
            return redirect()->route('retur.show', $retur)
                ->with('error', 'Hanya retur draft yang dapat diajukan.');
        }

        $retur->update(['status' => 'menunggu_approval']);

        $notifikasi->notifyRole(
            'admin_dinas',
            'Retur menunggu persetujuan',
            "Retur {$retur->nomor_retur} menunggu persetujuan.",
            route('retur.show', $retur),
        );

        return redirect()->route('retur.show', $retur)->with('success', 'Retur diajukan. Menunggu persetujuan.');
    }

    public function approve(Request $request, ReturObat $retur): RedirectResponse
    {
        $this->authorize('approve', $retur);

        if ($retur->status !== 'menunggu_approval') {
            return redirect()->route('retur.show', $retur)
                ->with('error', 'Hanya retur menunggu persetujuan yang dapat disetujui.');
        }

        $retur->update([
            'status' => 'disetujui',
            'tanggal_disetujui' => now()->toDateString(),
            'disetujui_oleh' => $request->user()->id,
        ]);

        return redirect()->route('retur.show', $retur)->with('success', 'Retur disetujui.');
    }

    public function reject(Request $request, ReturObat $retur): RedirectResponse
    {
        $this->authorize('approve', $retur);

        if ($retur->status !== 'menunggu_approval') {
            return redirect()->route('retur.show', $retur)
                ->with('error', 'Hanya retur menunggu persetujuan yang dapat ditolak.');
        }

        $data = $request->validate([
            'alasan_penolakan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $retur->update([
            'status' => 'ditolak',
            'tanggal_ditolak' => now()->toDateString(),
            'catatan' => trim(($retur->catatan ? $retur->catatan."\n" : '').'Ditolak: '.$data['alasan_penolakan']),
        ]);

        return redirect()->route('retur.show', $retur)->with('success', 'Retur ditolak.');
    }

    public function send(ReturObat $retur): RedirectResponse
    {
        $this->authorize('send', $retur);

        if ($retur->status !== 'disetujui') {
            return redirect()->route('retur.show', $retur)
                ->with('error', 'Hanya retur yang disetujui yang dapat dikirim.');
        }

        $retur->update([
            'status' => 'dalam_pengiriman',
            'tanggal_dikirim' => now()->toDateString(),
        ]);

        return redirect()->route('retur.show', $retur)->with('success', 'Retur dikirim.');
    }

    public function receive(ReturObat $retur, StokService $stokService): RedirectResponse
    {
        $this->authorize('receive', $retur);

        if ($retur->status !== 'dalam_pengiriman') {
            return redirect()->route('retur.show', $retur)
                ->with('error', 'Hanya retur dalam pengiriman yang dapat diterima.');
        }

        $stokService->prosesReturDiterima($retur->loadMissing('details'));
        $retur->update([
            'status' => 'diterima',
            'tanggal_diterima' => now()->toDateString(),
        ]);

        if ($retur->tipe_retur === 'gudang_ke_supplier') {
            $retur->update(['status' => 'selesai']);
        }

        return redirect()->route('retur.show', $retur)->with('success', 'Retur diterima. Stok disesuaikan.');
    }

    public function complete(ReturObat $retur): RedirectResponse
    {
        $this->authorize('complete', $retur);

        if ($retur->status !== 'diterima') {
            return redirect()->route('retur.show', $retur)
                ->with('error', 'Hanya retur yang sudah diterima yang dapat diselesaikan.');
        }

        $retur->update(['status' => 'selesai']);

        return redirect()->route('retur.show', $retur)->with('success', 'Retur selesai.');
    }

    /**
     * Batasi daftar sesuai aturan ReturObatPolicy::view.
     */
    private function scopeList($query, User $user): void
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin_dinas')) {
            return;
        }

        if ($user->hasRole('admin_gudang') && blank($user->fasilitas_kesehatan_id)) {
            $query->where(function ($q): void {
                $q->whereNull('fasilitas_pengirim_id')
                    ->orWhereNull('fasilitas_penerima_id');
            });

            return;
        }

        $faskesId = $user->fasilitas_kesehatan_id;

        if (blank($faskesId)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($faskesId): void {
            $q->where('fasilitas_pengirim_id', $faskesId)
                ->orWhere('fasilitas_penerima_id', $faskesId);
        });
    }

    /**
     * User faskes hanya boleh membuat tipe retur yang sesuai tipenya;
     * retur ke supplier wajib memilih supplier.
     */
    private function assertTipeBoleh(string $tipe, ?int $faskesId, array $data): void
    {
        if (filled($faskesId)) {
            $faskes = FasilitasKesehatan::find($faskesId);
            $boleh = $faskes?->tipe === 'pustu' ? 'pustu_ke_puskesmas' : 'puskesmas_ke_gudang';

            if ($tipe !== $boleh) {
                abort(422, 'Tipe retur tidak sesuai dengan faskes Anda.');
            }
        }

        if ($tipe === 'gudang_ke_supplier' && blank($data['supplier_id'] ?? null)) {
            abort(422, 'Retur ke supplier wajib memilih supplier.');
        }
    }

    /**
     * Tentukan pengirim/penerima dari tipe retur.
     * User faskes selalu menjadi pengirim; gudang = null.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function resolvePihak(string $tipe, ?int $userFaskesId, ?int $inputPenerima): array
    {
        return match ($tipe) {
            'pustu_ke_puskesmas' => [
                $userFaskesId,
                $inputPenerima ?? FasilitasKesehatan::whereKey($userFaskesId)->value('puskesmas_induk_id'),
            ],
            'puskesmas_ke_gudang' => [$userFaskesId, null],
            'gudang_ke_supplier' => [null, null],
            default => [$userFaskesId, $inputPenerima],
        };
    }

    private function defaultTipe(?int $faskesId): string
    {
        if (blank($faskesId)) {
            return 'gudang_ke_supplier';
        }

        $faskes = FasilitasKesehatan::find($faskesId);

        return $faskes?->tipe === 'pustu' ? 'pustu_ke_puskesmas' : 'puskesmas_ke_gudang';
    }

    /**
     * Simpan file bukti per baris ke disk public. Return rows + path bukti_foto.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function simpanBukti(Request $request, array $rows): array
    {
        foreach ($rows as $i => $row) {
            $file = $request->file("rows.{$i}.bukti");

            if ($file && $file->isValid()) {
                $rows[$i]['bukti_foto'] = $file->store('bukti-retur', 'public');
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncDetails(ReturObat $retur, array $rows): void
    {
        $retur->details()->delete();

        foreach ($rows as $row) {
            $batch = BatchStok::query()
                ->whereKey($row['batch_id'])
                ->where('obat_id', $row['obat_id'])
                ->when(
                    $retur->fasilitas_pengirim_id,
                    fn ($q) => $q->where('fasilitas_id', $retur->fasilitas_pengirim_id),
                    fn ($q) => $q->whereNull('fasilitas_id'),
                )
                ->first();

            if (! $batch) {
                abort(422, 'Batch tidak ditemukan di stok pengirim.');
            }

            if ($batch->jumlah < (int) $row['jumlah_retur']) {
                abort(422, "Stok batch {$batch->batch_number} tidak mencukupi (tersisa {$batch->jumlah}, diminta {$row['jumlah_retur']}).");
            }

            $retur->details()->create([
                'obat_id' => $row['obat_id'],
                'batch_id' => $batch->id,
                'jumlah_retur' => (int) $row['jumlah_retur'],
                'bukti_foto' => $row['bukti_foto'] ?? null,
                'catatan' => $row['keterangan'] ?? null,
            ]);
        }
    }
}
