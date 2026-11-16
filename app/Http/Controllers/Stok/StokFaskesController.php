<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\RiwayatStok;
use App\Models\StokFaskes;
use App\Models\User;
use App\Services\KalkulasiStokMinimumService;
use App\Support\FaskesAccess;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StokFaskesController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', StokFaskes::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'fasilitas', 'kategori', 'status_stok']);
        $sorts = ['jumlah' => 'jumlah'];
        $sort = $sorts[$request->query('sort')] ?? null;
        $sortDir = $request->query('sortDir') === 'desc' ? 'desc' : 'asc';

        $query = StokFaskes::query()->with(['obat:id,kode_obat,nama_obat,kategori,satuan', 'fasilitas:id,nama,tipe']);

        FaskesAccess::scopeQuery($query, $user);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('obat', fn ($q) => $q
            ->where('kode_obat', 'like', "%{$v}%")
            ->orWhere('nama_obat', 'like', "%{$v}%")));

        $query->when(! $lockedFaskes && ($filters['fasilitas'] ?? null), fn ($q, $v) => $q->where('fasilitas_id', $v));
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

        $perPage = (int) $request->query('per_page', $user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        if ($sort) {
            $query->orderBy($sort, $sortDir);
        } else {
            $query->orderBy('fasilitas_id')->orderBy('obat_id');
        }

        $stok = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $stok->items(),
                'meta' => TableMeta::from($stok),
            ]);
        }

        return view('stok.faskes.index', [
            'initial' => [
                'data' => $stok->items(),
                'meta' => TableMeta::from($stok),
                'filters' => $filters,
                'sort' => $request->query('sort'),
                'sortDir' => $sortDir,
            ],
            'filters' => $filters,
            'lockedFaskes' => $lockedFaskes,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'kategoriList' => Obat::query()->distinct()->orderBy('kategori')->pluck('kategori'),
            'kpi' => $this->kpi($user),
        ]);
    }

    public function show(Request $request, FasilitasKesehatan $fasilitas, int $obat): View
    {
        $stok = StokFaskes::query()
            ->with(['obat', 'fasilitas:id,nama,tipe'])
            ->where('fasilitas_id', $fasilitas->id)
            ->where('obat_id', $obat)
            ->firstOrFail();

        $this->authorize('view', $stok);

        $expiry = $request->query('expiry');

        $batches = BatchStok::query()
            ->with('sumberDana:id,nama')
            ->where('obat_id', $stok->obat_id)
            ->where('fasilitas_id', $fasilitas->id);

        match ($expiry) {
            'lewat' => $batches->whereDate('tanggal_expired', '<', now()->toDateString()),
            'd30' => $batches->whereBetween('tanggal_expired', [now()->toDateString(), now()->addDays(30)->toDateString()]),
            'd90' => $batches->whereBetween('tanggal_expired', [now()->toDateString(), now()->addDays(90)->toDateString()]),
            default => $batches->orderBy('tanggal_expired'),
        };

        $riwayat = RiwayatStok::query()
            ->with('user:id,name')
            ->where('obat_id', $stok->obat_id)
            ->where('fasilitas_id', $fasilitas->id)
            ->latest('tanggal')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('stok.faskes.show', [
            'stok' => $stok,
            'batches' => $batches->orderBy('tanggal_expired')->paginate(25, ['*'], 'batch_page'),
            'riwayat' => $riwayat,
            'expiry' => $expiry,
        ]);
    }

    public function recalculate(Request $request, KalkulasiStokMinimumService $service): RedirectResponse
    {
        $this->authorize('viewAny', StokFaskes::class);

        $updated = $service->kalkulasiFaskes();

        return redirect()->route('stok-faskes.index', $request->only(['search', 'fasilitas', 'kategori', 'status_stok']))
            ->with('success', "Stok minimum {$updated} record faskes berhasil dihitung ulang.");
    }

    /**
     * @return array<string, int>
     */
    private function kpi(User $user): array
    {
        $base = fn () => FaskesAccess::scopeQuery(StokFaskes::query(), $user);

        return [
            'total' => $base()->count(),
            'habis' => $base()->where('jumlah', '<=', 0)->count(),
            'menipis' => $base()->where('jumlah', '>', 0)
                ->whereColumn('jumlah', '<=', 'stok_minimum')
                ->where('stok_minimum', '>', 0)
                ->count(),
        ];
    }
}
