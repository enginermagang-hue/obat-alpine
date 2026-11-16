<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\FasilitasKesehatanRequest;
use App\Models\FasilitasKesehatan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FasilitasKesehatanController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FasilitasKesehatan::class);

        $filters = $request->only(['search', 'tipe', 'status']);

        $query = FasilitasKesehatan::query()->with('puskesmasInduk:id,nama');

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
            $q->where('kode_faskes', 'like', "%{$v}%")
                ->orWhere('nama', 'like', "%{$v}%")
                ->orWhere('pic', 'like', "%{$v}%");
        }));

        $query->when($filters['tipe'] ?? null, fn ($q, $v) => $q->where('tipe', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        $perPage = (int) ($request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $faskes = $query->orderBy('nama')->paginate($perPage)->withQueryString();

        return view('master.fasilitas.index', [
            'faskes' => $faskes,
            'filters' => $filters,
            'puskesmasList' => FasilitasKesehatan::query()
                ->where('tipe', 'puskesmas')
                ->orderBy('nama')
                ->get(['id', 'nama']),
        ]);
    }

    public function store(FasilitasKesehatanRequest $request): RedirectResponse
    {
        FasilitasKesehatan::create($request->validated());

        return redirect()->route('fasilitas.index')->with('success', 'Fasilitas kesehatan berhasil ditambahkan.');
    }

    public function update(FasilitasKesehatanRequest $request, FasilitasKesehatan $fasilitas): RedirectResponse
    {
        $fasilitas->update($request->validated());

        return redirect()->route('fasilitas.index')->with('success', 'Fasilitas kesehatan berhasil diperbarui.');
    }

    public function destroy(FasilitasKesehatan $fasilitas): RedirectResponse
    {
        $this->authorize('delete', $fasilitas);

        if ($fasilitas->users()->exists()
            || $fasilitas->stokFaskes()->exists()
            || $fasilitas->pustu()->exists()) {
            return redirect()->route('fasilitas.index')
                ->with('error', 'Faskes tidak dapat dihapus karena masih dipakai (user, stok, atau pustu turunan). Nonaktifkan saja.');
        }

        if (\App\Models\User::where('fasilitas_kesehatan_id', $fasilitas->id)->exists()
            || \App\Models\BatchStok::where('fasilitas_id', $fasilitas->id)->exists()
            || \App\Models\PenerimaanStok::where('fasilitas_id', $fasilitas->id)->exists()) {
            return redirect()->route('fasilitas.index')
                ->with('error', 'Faskes tidak dapat dihapus karena masih dipakai transaksi. Nonaktifkan saja.');
        }

        $fasilitas->delete();

        return redirect()->route('fasilitas.index')->with('success', 'Fasilitas kesehatan berhasil dihapus.');
    }

    public function deactivate(FasilitasKesehatan $fasilitas): RedirectResponse
    {
        $this->authorize('update', $fasilitas);

        $fasilitas->update(['status' => 'nonaktif']);

        return redirect()->route('fasilitas.index')->with('success', 'Faskes berhasil dinonaktifkan.');
    }
}
