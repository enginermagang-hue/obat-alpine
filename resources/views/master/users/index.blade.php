@extends('layouts.app')

@section('title', 'Pengguna & Role')
@section('subtitle', 'Akun pengguna dan hak akses')

@section('content')
    @php
        $formDefaults = [
            'name' => '', 'email' => '', 'fasilitas_kesehatan_id' => '', 'roles' => [],
        ];
        $openReset = $errors->has('password') && old('reset_id');
        $openForm = $errors->any() && ! $openReset;
    @endphp

    <div
        class="space-y-6"
        x-data="modal(@js(array_merge($formDefaults, old() ? collect(old())->only(array_keys($formDefaults))->toArray() : [])))"
        x-init="editing = @js(old('editing_id')); formOpen = @js($openForm); resetOpen = @js((bool) $openReset); resetId = @js(old('reset_id')); resetEmail = @js(old('reset_email'))"
        @user-create.window="openCreate()"
        @user-edit.window="openEdit($event.detail)"
        @user-confirm.window="openConfirm($event.detail)"
        @user-reset.window="resetOpen = true; resetId = $event.detail.id; resetEmail = $event.detail.email"
    >
        <div
            class="space-y-6"
            x-data="dataTable({
                url: '{{ route('users.index') }}',
                initial: @js($initial),
                filterKeys: ['search', 'role', 'faskes'],
            })"
        >
        <section class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-1 dark:border-slate-800">
            <a href="{{ route('users.index') }}" class="rounded-t-lg border-b-2 border-brand-600 px-4 py-2 text-sm font-semibold text-brand-700 dark:text-brand-300">
                Pengguna
            </a>
            <a href="{{ route('roles.index') }}" class="rounded-t-lg border-b-2 border-transparent px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                Role & Permission
            </a>
        </section>

        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\User::class)
                    <button type="button" class="btn btn-primary" @click="$dispatch('user-create')">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Pengguna
                    </button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('users.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <input type="search" name="search" x-model="filters.search" @input.debounce.300ms="applyFilter()" placeholder="Cari nama atau email…" class="form-input">
                </div>

                <select name="role" x-model="filters.role" @change="applyFilter()" class="form-select">
                    <option value="">Semua role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ str($role->name)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>

                <select name="faskes" x-model="filters.faskes" @change="applyFilter()" class="form-select">
                    <option value="">Semua faskes + dinas</option>
                    @foreach ($faskesList as $f)
                        <option value="{{ $f->id }}">{{ $f->nama }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <button type="button" class="btn btn-ghost" @click="reset()">Atur ulang</button>
                </div>
            </form>
            <noscript>
                <p class="px-5 pb-4 text-xs text-slate-500">Aktifkan JavaScript untuk filter & pagination tanpa reload.</p>
            </noscript>

            <div x-show="error" x-cloak class="mx-5 mb-4 flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                <span x-text="error"></span>
                <button type="button" class="font-medium underline" @click="load(meta.page)">Coba lagi</button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800" :class="{ 'opacity-50': loading }">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">
                                <button type="button" @click="setSort('name')" class="uppercase tracking-wide hover:underline">
                                    Nama <span x-show="sort === 'name'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">Faskes</th>
                            <th class="px-5 py-3 font-medium">Role</th>
                            <th class="px-5 py-3 font-medium">Aktif terakhir</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100" x-text="row.name"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="row.email"></p>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.fasilitas_kesehatan ? row.fasilitas_kesehatan.nama : 'Dinas'"></td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="r in row.roles" :key="r.id">
                                            <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300" x-text="r.name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"></span>
                                        </template>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400" x-text="row.last_active_at ? new Date(row.last_active_at).toLocaleString('id-ID') : '—'"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if (auth()->user()->hasPermissionTo('update_users'))
                                            <button type="button" class="btn-icon" title="Ubah"
                                                @click="$dispatch('user-edit', { id: row.id, name: row.name, email: row.email, fasilitas_kesehatan_id: row.fasilitas_kesehatan_id, roles: row.roles.map(r => r.name) })">
                                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                                            </button>
                                            <button type="button" class="btn-icon" title="Reset password"
                                                @click="$dispatch('user-reset', { id: row.id, email: row.email })">
                                                <x-heroicon-o-key class="h-4 w-4" />
                                            </button>
                                        @endif
                                        @if (auth()->user()->hasPermissionTo('delete_users'))
                                            <button type="button" class="btn-icon btn-icon-danger" title="Hapus"
                                                @click="$dispatch('user-confirm', { title: 'Hapus pengguna', message: 'Akun akan dihapus permanen. Ditolak bila punya riwayat transaksi.', action: '/users/' + row.id, method: 'DELETE', label: 'Hapus' })">
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada data yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ubah kata kunci atau atur ulang filter.</p>
                            </td>
                        </tr>
                        <tr x-show="loading">
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">Memuat…</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <x-ui.table-pagination />
        </section>
        </div>

        <x-ui.modal name="form" title="Data Pengguna" :wide="true">
            <form :action="editing ? '/users/' + editing : '{{ route('users.store') }}'" method="POST" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                <input type="hidden" name="editing_id" :value="editing">

                <div>
                    <label for="name" class="form-label">Nama *</label>
                    <input id="name" name="name" type="text" x-model="form.name" required class="form-input">
                    @error('name')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="form-label">Email *</label>
                    <input id="email" name="email" type="email" x-model="form.email" required class="form-input">
                    @error('email')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div x-show="! editing" x-cloak>
                    <label for="password" class="form-label">Password *</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="form-input">
                    @error('password')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div x-show="! editing" x-cloak>
                    <label for="password_confirmation" class="form-label">Ulangi password *</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-input">
                </div>

                <div class="sm:col-span-2">
                    <label for="fasilitas_kesehatan_id" class="form-label">Faskes (kosongkan untuk akun dinas)</label>
                    <select id="fasilitas_kesehatan_id" name="fasilitas_kesehatan_id" x-model="form.fasilitas_kesehatan_id" class="form-select">
                        <option value="">— Dinas —</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}">{{ $f->nama }}</option>
                        @endforeach
                    </select>
                    @error('fasilitas_kesehatan_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="roles" class="form-label">Role *</label>
                    <select id="roles" name="roles[]" multiple x-model="form.roles" class="form-select">
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}">{{ str($role->name)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                    @error('roles')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn btn-ghost" @click="formOpen = false">Batal</button>
                    <button type="submit" class="btn btn-primary" x-text="editing ? 'Simpan perubahan' : 'Tambah pengguna'"></button>
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

        <x-ui.modal name="reset" title="Reset Password">
            <form :action="resetId ? '/users/' + resetId + '/reset-password' : '#'" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="reset_id" :value="resetId">
                <input type="hidden" name="reset_email" :value="resetEmail">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Tetapkan password baru untuk <span class="font-medium text-slate-900 dark:text-white" x-text="resetEmail"></span>.
                </p>
                <div>
                    <label for="reset_password" class="form-label">Password baru *</label>
                    <input id="reset_password" name="password" type="password" autocomplete="new-password" required minlength="8" class="form-input">
                    @error('password')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reset_password_confirmation" class="form-label">Ulangi password baru *</label>
                    <input id="reset_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" class="form-input">
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost" @click="resetOpen = false">Batal</button>
                    <button type="submit" class="btn btn-primary">Reset password</button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
