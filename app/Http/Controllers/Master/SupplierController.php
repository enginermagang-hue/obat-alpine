<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
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

        $sorts = ['nama' => 'nama'];
        $sort = $sorts[$request->query('sort')] ?? 'nama';
        $sortDir = $request->query('sortDir') === 'desc' ? 'desc' : 'asc';

        $perPage = (int) $request->query('per_page', $request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $suppliers = $query->orderBy($sort, $sortDir)->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $suppliers->items(),
                'meta' => TableMeta::from($suppliers),
            ]);
        }

        return view('master.supplier.index', [
            'initial' => [
                'data' => $suppliers->items(),
                'meta' => TableMeta::from($suppliers),
                'filters' => $filters,
                'sort' => $request->query('sort'),
                'sortDir' => $sortDir,
            ],
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
