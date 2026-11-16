<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\ObatRequest;
use App\Models\Obat;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ObatController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Obat::class);

        $filters = $request->only(['search', 'kategori', 'bentuk', 'ven', 'status', 'metode']);

        $query = Obat::query();

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
            $q->where('kode_obat', 'like', "%{$v}%")
                ->orWhere('nama_obat', 'like', "%{$v}%")
                ->orWhere('nama_generik', 'like', "%{$v}%")
                ->orWhere('kategori', 'like', "%{$v}%");
        }));

        $query->when($filters['kategori'] ?? null, fn ($q, $v) => $q->where('kategori', $v));
        $query->when($filters['bentuk'] ?? null, fn ($q, $v) => $q->where('bentuk_sediaan', $v));
        $query->when($filters['ven'] ?? null, fn ($q, $v) => $q->where('ven_kategori', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));
        $query->when($filters['metode'] ?? null, fn ($q, $v) => $q->where('metode_stok', $v));

        $perPage = (int) ($request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $obat = $query->orderBy('kode_obat')->paginate($perPage)->withQueryString();

        $importRow = DB::table('imports')
            ->where('importer', 'obat')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        return view('master.obat.index', [
            'obat' => $obat,
            'filters' => $filters,
            'kategoriList' => Obat::query()->distinct()->orderBy('kategori')->pluck('kategori'),
            'importStatus' => $importRow ? (array) $importRow : null,
        ]);
    }

    public function store(ObatRequest $request): RedirectResponse
    {
        Obat::create($request->validated());

        return redirect()->route('obat.index')->with('success', 'Data obat berhasil ditambahkan.');
    }

    public function update(ObatRequest $request, Obat $obat): RedirectResponse
    {
        $obat->update($request->validated());

        return redirect()->route('obat.index')->with('success', 'Data obat berhasil diperbarui.');
    }

    public function destroy(Obat $obat): RedirectResponse
    {
        $this->authorize('delete', $obat);

        if ($obat->batchStok()->exists()) {
            return redirect()->route('obat.index')
                ->with('error', 'Obat tidak dapat dihapus karena masih memiliki riwayat batch. Nonaktifkan saja.');
        }

        $obat->delete();

        return redirect()->route('obat.index')->with('success', 'Data obat berhasil dihapus.');
    }

    public function deactivate(Obat $obat): RedirectResponse
    {
        $this->authorize('update', $obat);

        $obat->update(['status' => 'nonaktif']);

        return redirect()->route('obat.index')->with('success', 'Obat berhasil dinonaktifkan.');
    }
}
