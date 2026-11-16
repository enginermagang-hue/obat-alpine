<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::withCount('users')->orderBy('name')->get();

        $permissions = Permission::orderBy('name')->pluck('name');

        $groups = $permissions
            ->groupBy(fn (string $name) => preg_replace('/^(view|create|update|delete)_/', '', $name))
            ->sortKeys();

        $actions = ['view', 'create', 'update', 'delete'];

        return view('master.roles.index', [
            'roles' => $roles,
            'groups' => $groups,
            'actions' => $actions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name', 'regex:/^[a-z0-9_]+$/'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah dipakai.',
            'name.regex' => 'Nama role hanya huruf kecil, angka, dan garis bawah.',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return redirect()->route('roles.index')->with('success', 'Role berhasil ditambahkan.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id), 'regex:/^[a-z0-9_]+$/'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah dipakai.',
            'name.regex' => 'Nama role hanya huruf kecil, angka, dan garis bawah.',
        ]);

        if ($role->name === 'super_admin') {
            return redirect()->route('roles.index')->with('error', 'Role super_admin dilindungi dan tidak dapat diubah.');
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->name === 'super_admin') {
            return redirect()->route('roles.index')->with('error', 'Role super_admin dilindungi dan tidak dapat dihapus.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')->with('error', 'Role tidak dapat dihapus karena masih dipakai pengguna.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role berhasil dihapus.');
    }
}
