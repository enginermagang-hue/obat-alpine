<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Http\Requests\NeracaRequest;
use App\Models\FasilitasKesehatan;
use App\Models\NeracaTahunan;
use App\Services\NeracaTahunanService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NeracaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', NeracaTahunan::class);

        $user = $request->user();
        $filters = $request->only(['search', 'status', 'fasilitas', 'tahun']);

        $query = NeracaTahunan::query()->with(['fasilitas:id,nama', 'dibuatOleh:id,name'])
            ->withCount('details')
            ->withSum('details as nilai_stok', 'nilai_stok');

        if (filled($user->fasilitas_kesehatan_id)) {
            $query->where('fasilitas_id', $user->fasilitas_kesehatan_id);
        } elseif (($filters['fasilitas'] ?? null) !== null && $filters['fasilitas'] !== '') {
            $query->where('fasilitas_id', $filters['fasilitas']);
        }

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where('nomor_neraca', 'like', "%{$v}%"));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['tahun'] ?? null, fn ($q, $v) => $q->where('tahun', $v));

        $query->orderByDesc('tahun')->latest('id');

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $items = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
            ]);
        }

        return view('laporan.neraca.index', [
            'initial' => [
                'data' => $items->items(),
                'meta' => TableMeta::from($items),
                'filters' => $filters,
            ],
            'filters' => $filters,
            'lockedFaskes' => filled($user->fasilitas_kesehatan_id),
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', NeracaTahunan::class);

        $user = $request->user();

        return view('laporan.neraca.form', [
            'neraca' => null,
            'faskesNama' => $user->fasilitasKesehatan?->nama,
            'defaultFaskes' => $user->fasilitas_kesehatan_id,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
        ]);
    }

    public function store(NeracaRequest $request, NeracaTahunanService $service): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $fasilitasId = filled($user->fasilitas_kesehatan_id) ? (int) $user->fasilitas_kesehatan_id : null;

        if (NeracaTahunan::where('fasilitas_id', $fasilitasId)->where('tahun', $data['tahun'])->exists()) {
            return redirect()->back()->withInput()
                ->with('error', "Neraca tahun {$data['tahun']} untuk lokasi ini sudah ada.");
        }

        $neraca = DB::transaction(function () use ($data, $user, $fasilitasId, $service) {
            $neraca = NeracaTahunan::create([
                'nomor_neraca' => \App\Services\NomorFormatService::generate('neraca_tahunan', $fasilitasId, now()->toDateString()),
                'fasilitas_id' => $fasilitasId,
                'tahun' => $data['tahun'],
                'status' => 'draft',
                'catatan' => $data['catatan'] ?? null,
                'dibuat_oleh' => $user->id,
            ]);

            $service->generate($neraca);

            return $neraca;
        });

        return redirect()->route('neraca.show', $neraca)->with('success', "Neraca {$neraca->nomor_neraca} berhasil dibuat.");
    }

    public function show(NeracaTahunan $neraca): View
    {
        $this->authorize('view', $neraca);

        $neraca->loadMissing([
            'fasilitas:id,nama', 'dibuatOleh:id,name',
            'details.obat:id,kode_obat,nama_obat,satuan', 'details.sumberDanaDetails.sumberDana:id,nama',
        ]);

        return view('laporan.neraca.show', [
            'neraca' => $neraca,
        ]);
    }

    public function destroy(NeracaTahunan $neraca): RedirectResponse
    {
        $this->authorize('delete', $neraca);

        $neraca->details()->delete();
        $neraca->delete();

        return redirect()->route('neraca.index')->with('success', 'Neraca draft berhasil dihapus.');
    }

    public function finish(NeracaTahunan $neraca): RedirectResponse
    {
        $this->authorize('finish', $neraca);

        if ($neraca->status !== 'draft') {
            return redirect()->route('neraca.show', $neraca)
                ->with('error', 'Hanya neraca draft yang dapat diselesaikan.');
        }

        $neraca->update(['status' => 'selesai']);

        return redirect()->route('neraca.show', $neraca)->with('success', 'Neraca ditandai selesai (terkunci).');
    }

    public function reopen(NeracaTahunan $neraca): RedirectResponse
    {
        $this->authorize('reopen', $neraca);

        if ($neraca->status !== 'selesai') {
            return redirect()->route('neraca.show', $neraca)
                ->with('error', 'Hanya neraca selesai yang dapat dibuka kembali.');
        }

        $neraca->update(['status' => 'draft']);

        return redirect()->route('neraca.show', $neraca)->with('success', 'Neraca dibuka kembali ke draft.');
    }
}
