<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = $request->only(['search', 'status']);

        $query = Supplier::query();

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
            $q->where('nama', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")
                ->orWhere('npwp', 'like', "%{$v}%");
        }));

        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v));

        $perPage = (int) ($request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $suppliers = $query->orderBy('nama')->paginate($perPage)->withQueryString();

        return view('master.supplier.index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->penerimaanStok()->exists()) {
            return redirect()->route('supplier.index')
                ->with('error', 'Supplier tidak dapat dihapus karena masih punya riwayat penerimaan. Nonaktifkan saja.');
        }

        $supplier->delete();

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil dihapus.');
    }

    public function deactivate(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update(['status' => 'nonaktif']);

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil dinonaktifkan.');
    }
}
