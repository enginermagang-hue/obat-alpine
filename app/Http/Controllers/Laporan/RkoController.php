<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Http\Requests\RkoRequest;
use App\Models\FasilitasKesehatan;
use App\Models\LaporanRko;
use App\Models\Obat;
use App\Models\PengaturanLaporan;
use App\Services\LaporanRkoService;
use App\Services\NotificationService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RkoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', LaporanRko::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'status', 'fasilitas', 'tahun']);

        $query = LaporanRko::query()->with(['fasilitas:id,nama', 'dibuatOleh:id,name'])->withCount('details');

        if ($lockedFaskes) {
            $ids = FaskesAccess::visibleFasilitasIds($user);
            // Puskesmas melihat miliknya + pustu di bawahnya; pustu hanya miliknya.
            $query->whereIn('fasilitas_id', $ids);
        } elseif (($filters['fasilitas'] ?? null) !== null && $filters['fasilitas'] !== '') {
            $query->where('fasilitas_id', $filters['fasilitas']);
        }

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_rko', 'like', "%{$v}%"));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['tahun'] ?? null, fn ($q, $v) => $q->where('periode_tahun', $v));

        $query->orderByDesc('periode_tahun')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('laporan.rko.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'lockedFaskes' => $lockedFaskes,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
        ]);
    }

    public function create(Request $request, LaporanRkoService $service): View
    {
        $this->authorize('create', LaporanRko::class);

        $user = $request->user();
        $tahun = (int) (PengaturanLaporan::get('rko', 'periode_tahun', $user->fasilitas_kesehatan_id) ?: now()->addYear()->format('Y'));

        $preview = $service->previewData($user->fasilitas_kesehatan_id, $tahun);

        return view('laporan.rko.form', [
            'rko' => null,
            'faskesNama' => $user->fasilitasKesehatan?->nama,
            'tahun' => $tahun,
            'previewCount' => count($preview),
            'oldRows' => collect($preview)->map(fn (array $b) => [
                'obat_id' => $b['obat_id'] ?? null,
                'obat_label' => isset($b['obat_id']) ? (Obat::whereKey($b['obat_id'])->value('nama_obat') ?? '') : '',
                'pemakaian_tahun_sebelumnya' => $b['pemakaian_tahun_sebelumnya'] ?? 0,
                'rata_rata_pemakaian_bulanan' => $b['rata_rata_pemakaian_bulanan'] ?? 0,
                'stok_akhir' => $b['stok_akhir'] ?? 0,
                'kebutuhan_tahunan' => $b['kebutuhan_tahunan'] ?? 0,
                'usulan' => $b['usulan'] ?? 0,
                'harga_perkiraan' => isset($b['harga_perkiraan']) ? (string) $b['harga_perkiraan'] : '',
                'buffer_stock_persen' => isset($b['buffer_stock_persen']) ? (string) $b['buffer_stock_persen'] : '0',
            ])->toArray(),
        ]);
    }

    public function store(RkoRequest $request, LaporanRkoService $service): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $tahun = $this->periodeAktif($user);

        if (LaporanRko::where('fasilitas_id', $user->fasilitas_kesehatan_id)->where('periode_tahun', $tahun)->exists()) {
            return redirect()->back()->withInput()
                ->with('error', "RKO tahun {$tahun} untuk faskes ini sudah ada.");
        }

        $rko = DB::transaction(function () use ($data, $user, $tahun, $service) {
            $rko = LaporanRko::create([
                'nomor_rko' => \App\Services\NomorFormatService::generate('laporan_rko', $user->fasilitas_kesehatan_id, now()->toDateString()),
                'fasilitas_id' => $user->fasilitas_kesehatan_id,
                'periode_tahun' => $tahun,
                'status' => 'draft',
                'tanggal_pembuatan' => $data['tanggal_pembuatan'],
                'dibuat_oleh' => $user->id,
                'catatan' => $data['catatan'] ?? null,
            ]);

            if (! empty($data['rows'])) {
                $this->syncDetails($rko, $data['rows']);
            } else {
                foreach ($service->previewData($rko->fasilitas_id, $tahun) as $baris) {
                    if (isset($baris['ven_kategori_hidden'])) {
                        $baris['ven_kategori'] = $baris['ven_kategori_hidden'];
                        unset($baris['ven_kategori_hidden']);
                    }
                    $rko->details()->create($baris);
                }
            }

            $rko->update(['total_anggaran' => $rko->details()->sum('total_harga')]);

            return $rko;
        });

        return redirect()->route('rko.show', $rko)->with('success', "RKO {$rko->nomor_rko} berhasil dibuat (draft).");
    }

    public function show(LaporanRko $rko): View
    {
        $this->authorize('view', $rko);

        $rko->loadMissing([
            'fasilitas:id,nama', 'dibuatOleh:id,name', 'disetujuiOleh:id,name',
            'details.obat:id,kode_obat,nama_obat,satuan',
        ]);

        return view('laporan.rko.show', [
            'rko' => $rko,
        ]);
    }

    public function edit(LaporanRko $rko): View
    {
        $this->authorize('update', $rko);

        $rko->loadMissing('details');

        return view('laporan.rko.form', [
            'rko' => $rko,
            'faskesNama' => $rko->fasilitas?->nama,
            'tahun' => $rko->periode_tahun,
            'previewCount' => 0,
            'oldRows' => $rko->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'pemakaian_tahun_sebelumnya' => $d->pemakaian_tahun_sebelumnya,
                'rata_rata_pemakaian_bulanan' => $d->rata_rata_pemakaian_bulanan,
                'stok_akhir' => $d->stok_akhir,
                'kebutuhan_tahunan' => $d->kebutuhan_tahunan,
                'usulan' => $d->usulan,
                'harga_perkiraan' => $d->harga_perkiraan !== null ? (string) $d->harga_perkiraan : null,
                'buffer_stock_persen' => $d->buffer_stock_persen !== null ? (string) $d->buffer_stock_persen : null,
            ])->toArray(),
        ]);
    }

    public function update(RkoRequest $request, LaporanRko $rko): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($rko, $data): void {
            $rko->update([
                'tanggal_pembuatan' => $data['tanggal_pembuatan'],
                'catatan' => $data['catatan'] ?? null,
            ]);

            if (array_key_exists('rows', $data)) {
                $this->syncDetails($rko, $data['rows'] ?? []);
                $rko->update(['total_anggaran' => $rko->details()->sum('total_harga')]);
            }
        });

        return redirect()->route('rko.show', $rko)->with('success', 'RKO berhasil diperbarui.');
    }

    public function destroy(LaporanRko $rko): RedirectResponse
    {
        $this->authorize('delete', $rko);

        $rko->details()->delete();
        $rko->delete();

        return redirect()->route('rko.index')->with('success', 'RKO berhasil dihapus.');
    }

    public function submit(LaporanRko $rko, NotificationService $notifikasi): RedirectResponse
    {
        $this->authorize('update', $rko);

        if ($rko->status !== 'draft') {
            return redirect()->route('rko.show', $rko)
                ->with('error', 'Hanya RKO draft yang dapat diajukan.');
        }

        $rko->update([
            'status' => 'diajukan',
            'tanggal_pengajuan' => now()->toDateString(),
        ]);

        $notifikasi->notifyRole(
            'admin_dinas',
            'RKO menunggu persetujuan',
            "RKO {$rko->nomor_rko} dari {$rko->fasilitas?->nama} menunggu persetujuan.",
            route('rko.show', $rko),
        );

        return redirect()->route('rko.show', $rko)->with('success', 'RKO diajukan.');
    }

    public function approve(Request $request, LaporanRko $rko): RedirectResponse
    {
        $this->authorize('update', $rko);

        if ($rko->status !== 'diajukan') {
            return redirect()->route('rko.show', $rko)
                ->with('error', 'Hanya RKO yang diajukan dapat disetujui.');
        }

        $rko->update([
            'status' => 'disetujui',
            'tanggal_disetujui' => now()->toDateString(),
            'disetujui_oleh' => $request->user()->id,
        ]);

        return redirect()->route('rko.show', $rko)->with('success', 'RKO disetujui.');
    }

    public function reject(Request $request, LaporanRko $rko): RedirectResponse
    {
        $this->authorize('update', $rko);

        if ($rko->status !== 'diajukan') {
            return redirect()->route('rko.show', $rko)
                ->with('error', 'Hanya RKO yang diajukan dapat ditolak.');
        }

        $data = $request->validate([
            'catatan' => ['required', 'string', 'max:1000'],
        ], [
            'catatan.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $rko->update([
            'status' => 'ditolak',
            'catatan' => trim(($rko->catatan ? $rko->catatan."\n" : '').'Ditolak: '.$data['catatan']),
        ]);

        return redirect()->route('rko.show', $rko)->with('success', 'RKO ditolak.');
    }

    /**
     * Periode tahun RKO yang dibuka admin (fallback tahun depan).
     */
    private function periodeAktif(\App\Models\User $user): int
    {
        $periode = PengaturanLaporan::get('rko', 'periode_tahun', $user->fasilitas_kesehatan_id);

        return $periode !== null && $periode !== '' ? (int) $periode : (int) now()->addYear()->format('Y');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncDetails(LaporanRko $rko, array $rows): void
    {
        $rko->details()->delete();

        foreach ($rows as $row) {
            $bufferPersen = isset($row['buffer_stock_persen']) && $row['buffer_stock_persen'] !== '' ? (float) $row['buffer_stock_persen'] : 0;
            $usulan = (int) ($row['usulan'] ?? 0);
            $harga = isset($row['harga_perkiraan']) && $row['harga_perkiraan'] !== '' ? (float) $row['harga_perkiraan'] : null;
            $bufferQty = (int) round($usulan * $bufferPersen / 100);
            $totalKebutuhan = $usulan + $bufferQty;

            $rko->details()->create([
                'obat_id' => $row['obat_id'],
                'pemakaian_tahun_sebelumnya' => (int) ($row['pemakaian_tahun_sebelumnya'] ?? 0),
                'rata_rata_pemakaian_bulanan' => (int) ($row['rata_rata_pemakaian_bulanan'] ?? 0),
                'stok_akhir' => (int) ($row['stok_akhir'] ?? 0),
                'kebutuhan_tahunan' => (int) ($row['kebutuhan_tahunan'] ?? $usulan),
                'usulan' => $usulan,
                'harga_perkiraan' => $harga,
                'total_harga' => $harga !== null ? $usulan * $harga : null,
                'buffer_stock_persen' => $bufferPersen,
                'buffer_stok_qty' => $bufferQty,
                'total_kebutuhan' => $totalKebutuhan,
                'rencana_kebutuhan' => $totalKebutuhan,
            ]);
        }
    }
}
