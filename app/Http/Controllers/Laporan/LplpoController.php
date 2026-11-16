<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Http\Requests\LplpoRequest;
use App\Models\FasilitasKesehatan;
use App\Models\LaporanLplpo;
use App\Services\LaporanLplpoService;
use App\Services\NotificationService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LplpoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', LaporanLplpo::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'status', 'verifikasi', 'fasilitas', 'bulan', 'tahun']);

        $query = LaporanLplpo::query()->with(['fasilitas:id,nama', 'dibuatOleh:id,name'])->withCount('details')
            ->whereNull('parent_lplpo_id');

        if ($lockedFaskes) {
            $ids = FaskesAccess::visibleFasilitasIds($user);
            $query->whereIn('fasilitas_id', $ids);
        } elseif (($filters['fasilitas'] ?? null) !== null && $filters['fasilitas'] !== '') {
            $query->where('fasilitas_id', $filters['fasilitas']);
        }

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_laporan', 'like', "%{$v}%"));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['verifikasi'] ?? null, fn ($q, $v) => $q->where('status_verifikasi', $v));
        $query->when($filters['bulan'] ?? null, fn ($q, $v) => $q->where('periode_bulan', $v));
        $query->when($filters['tahun'] ?? null, fn ($q, $v) => $q->where('periode_tahun', $v));

        $query->orderByDesc('periode_tahun')->orderByDesc('periode_bulan')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('laporan.lplpo.index', [
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

    public function create(): View
    {
        $this->authorize('create', LaporanLplpo::class);

        $user = auth()->user();

        return view('laporan.lplpo.form', [
            'laporan' => null,
            'defaultFaskes' => $user->fasilitas_kesehatan_id,
            'faskesNama' => $user->fasilitasKesehatan?->nama,
            'oldRows' => [],
        ]);
    }

    public function store(LplpoRequest $request, LaporanLplpoService $service): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $lplpo = DB::transaction(function () use ($data, $user, $service) {
            $lplpo = LaporanLplpo::create([
                'nomor_laporan' => \App\Services\NomorFormatService::generate('laporan_lplpo', $user->fasilitas_kesehatan_id, $data['tanggal_pembuatan']),
                'fasilitas_id' => $user->fasilitas_kesehatan_id,
                'periode_bulan' => $data['periode_bulan'],
                'periode_tahun' => $data['periode_tahun'],
                'status' => 'draft',
                'tanggal_pembuatan' => $data['tanggal_pembuatan'],
                'dibuat_oleh' => $user->id,
                'catatan' => $data['catatan'] ?? null,
            ]);

            if (! empty($data['rows'])) {
                $this->syncDetails($lplpo, $data['rows']);
            } else {
                $service->generate($lplpo);
            }

            return $lplpo;
        });

        return redirect()->route('lplpo.show', $lplpo)->with('success', "LPLPO {$lplpo->nomor_laporan} berhasil dibuat.");
    }

    public function show(LaporanLplpo $lplpo, LaporanLplpoService $service): View
    {
        $this->authorize('view', $lplpo);

        $lplpo->loadMissing(['fasilitas:id,nama', 'dibuatOleh:id,name', 'verifikator:id,name', 'details.obat:id,kode_obat,nama_obat,satuan', 'revisiLplpo:id,parent_lplpo_id,nomor_laporan,status']);

        return view('laporan.lplpo.show', [
            'laporan' => $lplpo,
            'peringatan' => $service->validate($lplpo),
        ]);
    }

    public function edit(LaporanLplpo $lplpo): View
    {
        $this->authorize('update', $lplpo);

        $lplpo->loadMissing('details');

        return view('laporan.lplpo.form', [
            'laporan' => $lplpo,
            'defaultFaskes' => $lplpo->fasilitas_id,
            'faskesNama' => $lplpo->fasilitas?->nama,
            'oldRows' => $lplpo->details->map(fn ($d) => [
                'obat_id' => $d->obat_id,
                'obat_label' => $d->obat ? "{$d->obat->kode_obat} — {$d->obat->nama_obat}" : '',
                'stok_awal' => $d->stok_awal,
                'jumlah_masuk' => $d->jumlah_masuk,
                'jumlah_keluar' => $d->jumlah_keluar,
                'stok_optimum' => $d->stok_optimum,
                'permintaan_selanjutnya' => $d->permintaan_selanjutnya,
                'keterangan' => $d->keterangan,
            ])->toArray(),
        ]);
    }

    public function update(LplpoRequest $request, LaporanLplpo $lplpo): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($lplpo, $data): void {
            $lplpo->update([
                'periode_bulan' => $data['periode_bulan'],
                'periode_tahun' => $data['periode_tahun'],
                'tanggal_pembuatan' => $data['tanggal_pembuatan'],
                'catatan' => $data['catatan'] ?? null,
            ]);

            if (array_key_exists('rows', $data)) {
                $this->syncDetails($lplpo, $data['rows'] ?? []);
            }
        });

        return redirect()->route('lplpo.show', $lplpo)->with('success', 'LPLPO berhasil diperbarui.');
    }

    public function destroy(LaporanLplpo $lplpo): RedirectResponse
    {
        $this->authorize('delete', $lplpo);

        $lplpo->details()->delete();
        $lplpo->delete();

        return redirect()->route('lplpo.index')->with('success', 'LPLPO draft berhasil dihapus.');
    }

    public function regenerate(LaporanLplpo $lplpo, LaporanLplpoService $service): RedirectResponse
    {
        $this->authorize('update', $lplpo);

        $service->generate($lplpo);

        return redirect()->route('lplpo.show', $lplpo)->with('success', 'Detail LPLPO dibuat ulang dari riwayat stok.');
    }

    public function submit(LaporanLplpo $lplpo, NotificationService $notifikasi): RedirectResponse
    {
        $this->authorize('update', $lplpo);

        if ($lplpo->status !== 'draft') {
            return redirect()->route('lplpo.show', $lplpo)
                ->with('error', 'Hanya LPLPO draft yang dapat diajukan.');
        }

        $lplpo->update([
            'status_verifikasi' => LaporanLplpo::VERIF_MENUNGGU,
            'tanggal_kirim' => now(),
        ]);

        $notifikasi->notifyRole(
            'admin_dinas',
            'LPLPO menunggu verifikasi',
            "LPLPO {$lplpo->nomor_laporan} dari {$lplpo->fasilitas?->nama} menunggu verifikasi.",
            route('lplpo.show', $lplpo),
        );

        return redirect()->route('lplpo.show', $lplpo)->with('success', 'LPLPO diajukan untuk verifikasi.');
    }

    public function verify(Request $request, LaporanLplpo $lplpo): RedirectResponse
    {
        $this->authorize('verify', $lplpo);

        if ($lplpo->status_verifikasi !== LaporanLplpo::VERIF_MENUNGGU) {
            return redirect()->route('lplpo.show', $lplpo)
                ->with('error', 'Hanya LPLPO menunggu verifikasi yang dapat diproses.');
        }

        $lplpo->update([
            'status' => LaporanLplpo::STATUS_SELESAI,
            'status_verifikasi' => LaporanLplpo::VERIF_DIVERIFIKASI,
            'tanggal_verifikasi' => now(),
            'verifikator_id' => $request->user()->id,
        ]);

        return redirect()->route('lplpo.show', $lplpo)->with('success', 'LPLPO diverifikasi.');
    }

    public function requestRevision(Request $request, LaporanLplpo $lplpo): RedirectResponse
    {
        $this->authorize('verify', $lplpo);

        if ($lplpo->status_verifikasi !== LaporanLplpo::VERIF_MENUNGGU) {
            return redirect()->route('lplpo.show', $lplpo)
                ->with('error', 'Hanya LPLPO menunggu verifikasi yang dapat diminta revisi.');
        }

        $data = $request->validate([
            'catatan_verifikator' => ['required', 'string', 'max:1000'],
        ], [
            'catatan_verifikator.required' => 'Catatan revisi wajib diisi.',
        ]);

        $lplpo->update([
            'status_verifikasi' => LaporanLplpo::VERIF_REVISI,
            'catatan_verifikator' => $data['catatan_verifikator'],
        ]);

        return redirect()->route('lplpo.show', $lplpo)->with('success', 'LPLPO dikembalikan untuk revisi.');
    }

    public function revise(LaporanLplpo $lplpo): RedirectResponse
    {
        $this->authorize('create', LaporanLplpo::class);

        if ($lplpo->fasilitas_id !== auth()->user()->fasilitas_kesehatan_id) {
            abort(403);
        }

        $baru = DB::transaction(function () use ($lplpo) {
            $baru = $lplpo->replicate(['nomor_laporan', 'status', 'status_verifikasi', 'tanggal_kirim', 'tanggal_verifikasi', 'verifikator_id', 'catatan_verifikator']);
            $baru->nomor_laporan = \App\Services\NomorFormatService::generate('laporan_lplpo', $lplpo->fasilitas_id, now()->toDateString());
            $baru->status = 'draft';
            $baru->status_verifikasi = null;
            $baru->tanggal_kirim = null;
            $baru->tanggal_verifikasi = null;
            $baru->verifikator_id = null;
            $baru->catatan_verifikator = null;
            $baru->parent_lplpo_id = $lplpo->id;
            $baru->dibuat_oleh = auth()->id();
            $baru->save();

            foreach ($lplpo->details as $detail) {
                $baru->details()->create($detail->only([
                    'obat_id', 'stok_awal', 'jumlah_masuk', 'jumlah_keluar', 'sisa_stok',
                    'stok_optimum', 'permintaan_selanjutnya', 'sudah_diminta', 'keterangan',
                ]));
            }

            return $baru;
        });

        return redirect()->route('lplpo.edit', $baru)->with('success', "Revisi {$baru->nomor_laporan} dibuat dari {$lplpo->nomor_laporan}.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncDetails(LaporanLplpo $lplpo, array $rows): void
    {
        $lplpo->details()->delete();

        foreach ($rows as $row) {
            $awal = (int) ($row['stok_awal'] ?? 0);
            $masuk = (int) ($row['jumlah_masuk'] ?? 0);
            $keluar = (int) ($row['jumlah_keluar'] ?? 0);

            $lplpo->details()->create([
                'obat_id' => $row['obat_id'],
                'stok_awal' => $awal,
                'jumlah_masuk' => $masuk,
                'jumlah_keluar' => $keluar,
                'sisa_stok' => $awal + $masuk - $keluar,
                'stok_optimum' => $row['stok_optimum'] ?? null,
                'permintaan_selanjutnya' => (int) ($row['permintaan_selanjutnya'] ?? 0),
                'keterangan' => $row['keterangan'] ?? null,
            ]);
        }
    }
}
