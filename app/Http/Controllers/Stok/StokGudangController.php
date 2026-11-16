<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\BatchStok;
use App\Models\Obat;
use App\Models\RiwayatStok;
use App\Models\StokGudang;
use App\Services\KalkulasiStokMinimumService;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StokGudangController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', StokGudang::class);

        $filters = $request->only(['search', 'kategori', 'status_stok']);
        $sorts = ['jumlah' => 'jumlah'];
        $sort = $sorts[$request->query('sort')] ?? null;
        $sortDir = $request->query('sortDir') === 'desc' ? 'desc' : 'asc';

        $query = StokGudang::query()->with('obat:id,kode_obat,nama_obat,kategori,satuan');

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('obat', fn ($q) => $q
            ->where('kode_obat', 'like', "%{$v}%")
            ->orWhere('nama_obat', 'like', "%{$v}%")));

        $query->when($filters['kategori'] ?? null, fn ($q, $v) => $q->whereHas('obat', fn ($q) => $q->where('kategori', $v)));

        match ($filters['status_stok'] ?? null) {
            'habis' => $query->where('jumlah', '<=', 0),
            'menipis' => $query->where('jumlah', '>', 0)->whereColumn('jumlah', '<=', 'stok_minimum')->where('stok_minimum', '>', 0),
            'tersedia' => $query->where(function ($q): void {
                $q->where('jumlah', '>', 0)->where(function ($q): void {
                    $q->where('stok_minimum', '<=', 0)->orWhereColumn('jumlah', '>', 'stok_minimum');
                });
            }),
            default => null,
        };

        $perPage = (int) $request->query('per_page', $request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($sort) {
            $query->orderBy($sort, $sortDir);
        } else {
            $query->whereHas('obat', fn ($q) => $q->orderBy('nama_obat'))->orderBy('obat_id');
        }

        $stok = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $stok->items(),
                'meta' => TableMeta::from($stok),
            ]);
        }

        return view('stok.gudang.index', [
            'initial' => [
                'data' => $stok->items(),
                'meta' => TableMeta::from($stok),
                'filters' => $filters,
                'sort' => $request->query('sort'),
                'sortDir' => $sortDir,
            ],
            'filters' => $filters,
            'kategoriList' => Obat::query()->distinct()->orderBy('kategori')->pluck('kategori'),
            'kpi' => $this->kpi(),
        ]);
    }

    public function show(Obat $obat): View
    {
        $this->authorize('viewAny', StokGudang::class);

        $stok = StokGudang::query()->where('obat_id', $obat->id)->first();

        $batches = BatchStok::query()
            ->with('sumberDana:id,nama')
            ->where('obat_id', $obat->id)
            ->whereNull('fasilitas_id')
            ->orderBy('tanggal_expired')
            ->paginate(25, ['*'], 'batch_page');

        $riwayat = RiwayatStok::query()
            ->with('user:id,name')
            ->where('obat_id', $obat->id)
            ->whereNull('fasilitas_id')
            ->latest('tanggal')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('stok.gudang.show', [
            'obat' => $obat,
            'stok' => $stok,
            'batches' => $batches,
            'riwayat' => $riwayat,
            'nilai' => BatchStok::query()
                ->where('obat_id', $obat->id)
                ->whereNull('fasilitas_id')
                ->selectRaw('COALESCE(SUM(jumlah * harga_beli), 0) as total')
                ->value('total'),
        ]);
    }

    public function recalculate(KalkulasiStokMinimumService $service): RedirectResponse
    {
        $this->authorize('viewAny', StokGudang::class);

        $updated = $service->kalkulasiGudang();

        return redirect()->route('stok-gudang.index')
            ->with('success', "Stok minimum {$updated} obat berhasil dihitung ulang.");
    }

    /**
     * @return array<string, int|float>
     */
    private function kpi(): array
    {
        return [
            'total' => StokGudang::count(),
            'habis' => StokGudang::where('jumlah', '<=', 0)->count(),
            'menipis' => StokGudang::where('jumlah', '>', 0)
                ->whereColumn('jumlah', '<=', 'stok_minimum')
                ->where('stok_minimum', '>', 0)
                ->count(),
            'nilai' => (float) BatchStok::query()
                ->whereNull('fasilitas_id')
                ->selectRaw('COALESCE(SUM(jumlah * harga_beli), 0) as total')
                ->value('total'),
        ];
    }
}
