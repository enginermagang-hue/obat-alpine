@extends('layouts.app')

@section('title', 'Role & Permission')
@section('subtitle', 'Hak akses per role')

@section('content')
    @php
        $formDefaults = ['name' => '', 'permissions' => []];
        $openForm = $errors->any();
    @endphp

    <div
        class="space-y-6"
        x-data="modal(@js(array_merge($formDefaults, old() ? collect(old())->only(array_keys($formDefaults))->toArray() : [])))"
        x-init="editing = @js(old('editing_id')); formOpen = @js($openForm)"
    >
        <section class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-1 dark:border-slate-800">
            <a href="{{ route('users.index') }}" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                Pengguna
            </a>
            <a href="{{ route('roles.index') }}" class="rounded-t-lg border-b-2 border-brand-600 px-4 py-2 text-sm font-semibold text-brand-700 dark:text-brand-300">
                Role & Permission
            </a>
        </section>

        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', Spatie\Permission\Models\Role::class)
                    <button type="button" class="btn btn-primary" @click="openCreate()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Role
                    </button>
                @endcan
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ($roles as $role)
                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ str($role->name)->replace('_', ' ')->title() }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $role->users_count }} pengguna &middot; {{ $role->permissions_count ?? $role->permissions->count() }} permission</p>
                        </div>
                        <div class="flex items-center gap-1">
                            @can('update', $role)
                                @if ($role->name !== 'super_admin')
                                    <button type="button" class="btn-icon" title="Ubah"
                                        @click="openEdit(@js(['id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name')->toArray()]))">
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>
                                @endif
                            @endcan
                            @can('delete', $role)
                                @if ($role->name !== 'super_admin')
                                    <button type="button" class="btn-icon btn-icon-danger" title="Hapus"
                                        @click="openConfirm({ title: 'Hapus role', message: @js('Role akan dihapus permanen. Ditolak bila masih dipakai pengguna.'), action: '{{ route('roles.destroy', $role) }}', method: 'DELETE', label: 'Hapus' })">
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1 px-5 py-4">
                        @forelse ($role->permissions->take(12) as $perm)
                            <x-ui.badge tone="slate">{{ $perm->name }}</x-ui.badge>
                        @empty
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tanpa permission.</p>
                        @endforelse
                        @if ($role->permissions->count() > 12)
                            <x-ui.badge tone="brand">+{{ $role->permissions->count() - 12 }} lagi</x-ui.badge>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <x-ui.modal name="form" title="Data Role" :wide="true">
            <form :action="editing ? '/roles/' + editing : '{{ route('roles.store') }}'" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                <input type="hidden" name="editing_id" :value="editing">

                <div>
                    <label for="name" class="form-label">Nama role * <span class="font-normal text-slate-400">(huruf kecil, angka, garis bawah)</span></label>
                    <input id="name" name="name" type="text" x-model="form.name" required pattern="[a-z0-9_]+" class="form-input">
                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <p class="form-label">Permission</p>
                    <div class="max-h-80 space-y-4 overflow-y-auto rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        @foreach ($groups as $resource => $perms)
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ str($resource)->replace('_', ' ') }}</p>
                                    <button type="button" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-300" @click="form.permissions = [...new Set([...form.permissions, ...@js($perms->values()->toArray())])]">
                                        Pilih semua
                                    </button>
                                </div>
                                <div class="grid gap-1 sm:grid-cols-2">
                                    @foreach ($perms as $perm)
                                        @php $action = in_array(explode('_', $perm)[0], $actions) ? explode('_', $perm)[0] : 'lainnya'; @endphp
                                        <label class="form-check">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm }}" x-model="form.permissions" class="form-checkbox">
                                            <span><span class="font-medium">{{ ucfirst($action) }}</span> <span class="text-slate-400">{{ $perm }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('permissions')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost" @click="formOpen = false">Batal</button>
                    <button type="submit" class="btn btn-primary" x-text="editing ? 'Simpan perubahan' : 'Tambah role'"></button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal name="confirm">
            <form :action="confirm.action" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="confirm.method ?? 'DELETE'">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="confirm.title"></h2>
                <p class="text-sm text-slate-600 dark:text-slate-300" x-text="confirm.message"></p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost" @click="confirmOpen = false">Batal</button>
                    <button type="submit" class="btn btn-danger-solid" x-text="confirm.label ?? 'Hapus'"></button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
