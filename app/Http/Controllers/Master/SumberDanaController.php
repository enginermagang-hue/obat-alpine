<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\SumberDanaRequest;
use App\Models\SumberDana;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SumberDanaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SumberDana::class);

        $filters = $request->only(['search', 'tahun', 'status']);

        $query = SumberDana::query();

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
            $q->where('kode', 'like', "%{$v}%")
                ->orWhere('nama', 'like', "%{$v}%");
        }));

        $query->when($filters['tahun'] ?? null, fn ($q, $v) => $q->where('tahun', $v));
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        $perPage = (int) ($request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $dana = $query->orderByDesc('tahun')->orderBy('kode')->paginate($perPage)->withQueryString();

        return view('master.sumber-dana.index', [
            'dana' => $dana,
            'filters' => $filters,
            'tahunList' => SumberDana::query()->distinct()->orderByDesc('tahun')->pluck('tahun'),
        ]);
    }

    public function store(SumberDanaRequest $request): RedirectResponse
    {
        SumberDana::create($request->validated());

        return redirect()->route('sumber-dana.index')->with('success', 'Sumber dana berhasil ditambahkan.');
    }

    public function update(SumberDanaRequest $request, SumberDana $sumber_dana): RedirectResponse
    {
        $sumber_dana->update($request->validated());

        return redirect()->route('sumber-dana.index')->with('success', 'Sumber dana berhasil diperbarui.');
    }

    public function destroy(SumberDana $sumber_dana): RedirectResponse
    {
        $this->authorize('delete', $sumber_dana);

        if ($sumber_dana->batchStok()->exists()
            || $sumber_dana->penerimaanStok()->exists()
            || $sumber_dana->laporanRko()->exists()
            || $sumber_dana->sumberDanaPenggunaans()->exists()) {
            return redirect()->route('sumber-dana.index')
                ->with('error', 'Sumber dana tidak dapat dihapus karena masih dipakai transaksi. Nonaktifkan saja.');
        }

        $sumber_dana->delete();

        return redirect()->route('sumber-dana.index')->with('success', 'Sumber dana berhasil dihapus.');
    }

    public function deactivate(SumberDana $sumber_dana): RedirectResponse
    {
        $this->authorize('update', $sumber_dana);

        $sumber_dana->update(['status' => 'nonaktif']);

        return redirect()->route('sumber-dana.index')->with('success', 'Sumber dana berhasil dinonaktifkan.');
    }
}
