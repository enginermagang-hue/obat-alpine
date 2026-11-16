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
                    <button type="button" class="btn btn-primary" @click="openCreate()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Pengguna
                    </button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('users.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama atau email…" class="form-input">
                </div>

                <select name="role" class="form-select">
                    <option value="">Semua role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ str($role->name)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>

                <select name="faskes" class="form-select">
                    <option value="">Semua faskes + dinas</option>
                    @foreach ($faskesList as $f)
                        <option value="{{ $f->id }}" @selected((string) ($filters['faskes'] ?? '') === (string) $f->id)>{{ $f->nama }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a href="{{ route('users.index') }}" class="btn btn-ghost">Atur ulang</a>
                </div>
            </form>

            @if ($users->isEmpty())
                <x-ui.empty-state title="Belum ada pengguna" hint="Tambahkan akun pengguna." icon="users" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Faskes</th>
                                <th class="px-5 py-3 font-medium">Role</th>
                                <th class="px-5 py-3 font-medium">Aktif terakhir</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($users as $row)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $row->name }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row->email }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->fasilitasKesehatan?->nama ?? 'Dinas' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($row->roles as $r)
                                                <x-ui.badge tone="brand">{{ str($r->name)->replace('_', ' ')->title() }}</x-ui.badge>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $row->last_active_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @can('update', $row)
                                                <button type="button" class="btn-icon" title="Ubah"
                                                    @click="openEdit(@js(array_merge($row->only(['id', 'name', 'email', 'fasilitas_kesehatan_id']), ['roles' => $row->roles->pluck('name')->toArray()])))">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                </button>
                                                <button type="button" class="btn-icon" title="Reset password"
                                                    @click="resetOpen = true; resetId = {{ $row->id }}; resetEmail = @js($row->email)">
                                                    <x-heroicon-o-key class="h-4 w-4" />
                                                </button>
                                            @endcan
                                            @can('delete', $row)
                                                <button type="button" class="btn-icon btn-icon-danger" title="Hapus"
                                                    @click="openConfirm({ title: 'Hapus pengguna', message: @js('Akun akan dihapus permanen. Ditolak bila punya riwayat transaksi.'), action: '/users/' + {{ $row->id }}, method: 'DELETE', label: 'Hapus' })">
                                                    <x-heroicon-o-trash class="h-4 w-4" />
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.pagination :paginator="$users" />
            @endif
        </section>

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
