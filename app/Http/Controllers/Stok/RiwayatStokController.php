<?php

namespace App\Http\Controllers\Stok;

use App\Http\Controllers\Controller;
use App\Models\FasilitasKesehatan;
use App\Models\Obat;
use App\Models\RiwayatStok;
use App\Support\FaskesAccess;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiwayatStokController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', RiwayatStok::class);

        $user = $request->user();
        $lockedFaskes = filled($user->fasilitas_kesehatan_id);

        $filters = $request->only(['search', 'tipe', 'fasilitas', 'obat', 'dari', 'sampai', 'user']);

        $query = RiwayatStok::query()->with([
            'obat:id,kode_obat,nama_obat',
            'fasilitas:id,nama',
            'user:id,name',
        ]);

        FaskesAccess::scopeQuery($query, $user);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q
            ->where('keterangan', 'like', "%{$v}%")
            ->orWhereHas('obat', fn ($q) => $q
                ->where('kode_obat', 'like', "%{$v}%")
                ->orWhere('nama_obat', 'like', "%{$v}%")));

        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe', $v));

        if (! $lockedFaskes) {
            $query->when($filters['fasilitas'] ?? null, fn ($q, $v) => $q->where('fasilitas_id', $v));
        }

        $query->when($filters['obat'] ?? null, fn ($q, $v) => $q->where('obat_id', $v));
        $query->when($filters['user'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$v}%")));
        $query->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '>=', $v));
        $query->when($filters['sampai'] ?? null, fn ($q, $v) => $q->whereDate('tanggal', '<=', $v));

        $query->latest('tanggal')->latest('id');

        $perPage = (int) ($user->preferences->items_per_halaman ?? 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $riwayat = $query->paginate($perPage)->withQueryString();

        return view('stok.riwayat.index', [
            'riwayat' => $riwayat,
            'filters' => $filters,
            'lockedFaskes' => $lockedFaskes,
            'faskesList' => FaskesAccess::scopeQuery(FasilitasKesehatan::query()->orderBy('nama'), $user)->get(['id', 'nama']),
            'tipeList' => ['masuk', 'keluar', 'distribusi_masuk', 'distribusi_keluar', 'rusak', 'hilang', 'expired', 'opname', 'penyesuaian'],
            'obatTerpakai' => Obat::query()
                ->whereIn('id', fn ($q) => $q->select('obat_id')->from('riwayat_stok')->distinct())
                ->orderBy('nama_obat')
                ->limit(500)
                ->get(['id', 'nama_obat']),
        ]);
    }
}
