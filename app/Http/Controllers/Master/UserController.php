<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\FasilitasKesehatan;
use App\Models\PenerimaanStok;
use App\Models\User;
use App\Support\TableMeta;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role', 'faskes']);

        $query = User::query()->with(['fasilitasKesehatan:id,nama', 'roles:id,name']);

        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
            $q->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%");
        }));

        $query->when($filters['role'] ?? null, fn ($q, $v) => $q->whereHas('roles', fn ($q) => $q->where('name', $v)));
        $query->when($filters['faskes'] ?? null, fn ($q, $v) => $q->where('fasilitas_kesehatan_id', $v));

        $sorts = ['name' => 'name', 'email' => 'email'];
        $sort = $sorts[$request->query('sort')] ?? 'name';
        $sortDir = $request->query('sortDir') === 'desc' ? 'desc' : 'asc';

        $perPage = (int) $request->query('per_page', $request->user()->preferences->items_per_halaman ?? 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $users = $query->orderBy($sort, $sortDir)->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $users->items(),
                'meta' => TableMeta::from($users),
            ]);
        }

        return view('master.users.index', [
            'initial' => [
                'data' => $users->items(),
                'meta' => TableMeta::from($users),
                'filters' => $filters,
                'sort' => $request->query('sort'),
                'sortDir' => $sortDir,
            ],
            'filters' => $filters,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'faskesList' => FasilitasKesehatan::query()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'fasilitas_kesehatan_id' => $data['fasilitas_kesehatan_id'] ?? null,
        ]);

        $user->syncRoles($data['roles']);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'fasilitas_kesehatan_id' => $data['fasilitas_kesehatan_id'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->syncRoles($data['roles']);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if (request()->user()->is($user)) {
            return redirect()->route('users.index')->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        if (PenerimaanStok::where('user_id', $user->id)->exists()
            || DB::table('imports')->where('user_id', $user->id)->exists()
            || DB::table('exports')->where('user_id', $user->id)->exists()) {
            return redirect()->route('users.index')
                ->with('error', 'Pengguna tidak dapat dihapus karena punya riwayat transaksi (penerimaan/import/export).');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dihapus.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return redirect()->route('users.index')->with('success', "Password {$user->email} berhasil direset.");
    }
}
