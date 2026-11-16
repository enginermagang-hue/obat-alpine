<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\BatchStok;
use App\Models\FasilitasKesehatan;
use App\Models\User;
use App\Support\FaskesAccess;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BatchStok::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'fasilitas', 'status', 'expiry']);

        $query = BatchStok::query()->with(['obat:id,kode_obat,nama_obat,satuan', 'fasilitas:id,nama,tipe', 'sumberDana:id,nama']);

        FaskesAccess::scopeQuery($query, $user);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
            $q->where('batch_number', 'like', "%{$v}%")
                ->orWhereHas('obat', fn ($q) => $q
                    ->where('kode_obat', 'like', "%{$v}%")
                    ->orWhere('nama_obat', 'like', "%{$v}%"));
        }));

        if (! $lockedFaskes) {
            $query->when($filters['fasilitas'] ?? null, function ($q, $v): void {
                if ($v === 'gudang') {
                    $q->whereNull('fasilitas_id');
                } else {
                    $q->where('fasilitas_id', $v);
                }
            });
        }

        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        match ($filters['expiry'] ?? null) {
            'lewat' => $query->whereDate('tanggal_expired', '<', now()->toDateString()),
            'd30' => $query->whereBetween('tanggal_expired', [now()->toDateString(), now()->addDays(30)->toDateString()]),
            'd90' => $query->whereBetween('tanggal_expired', [now()->toDateString(), now()->addDays(90)->toDateString()]),
            default => null,
        };

        $query->orderBy('tanggal_expired')->orderBy('id');

        $perPage = (int) ($user->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $batches = $query->paginate($perPage)->withQueryString();

        return view('stok.batch.index', [
            'batches' => $batches,
            'filters' => $filters,
            'lockedFaskes' => $lockedFaskes,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'statusList' => ['tersedia', 'karantina', 'expired', 'dimusnahkan'],
            'kpi' => $this->kpi($user),
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function kpi(User $user): array
    {
        $today = now()->toDateString();
        $base = fn () => FaskesAccess::scopeQuery(BatchStok::query()->where('jumlah', '>', 0), $user);

        return [
            'total' => $base()->count(),
            'nilai' => (float) $base()->selectRaw('COALESCE(SUM(jumlah * harga_beli), 0) as total')->value('total'),
            'lewat' => $base()->whereDate('tanggal_expired', '<', $today)->count(),
            'd90' => $base()->whereBetween('tanggal_expired', [$today, now()->addDays(90)->toDateString()])->count(),
        ];
    }
}
