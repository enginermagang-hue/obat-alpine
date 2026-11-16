@extends('layouts.app')

@section('title', 'Supplier')
@section('subtitle', 'Rekanan pengadaan obat')

@section('content')
    @php
        $formDefaults = [
            'nama' => '', 'alamat' => '', 'telepon' => '', 'email' => '',
            'npwp' => '', 'status' => 'aktif',
        ];
        $openForm = $errors->any();
    @endphp

    <div
        class="space-y-6"
        x-data="modal(@js(array_merge($formDefaults, old() ? collect(old())->only(array_keys($formDefaults))->toArray() : [])))"
        x-init="editing = @js(old('editing_id')); formOpen = @js($openForm)"
        @supplier-create.window="openCreate()"
        @supplier-edit.window="openEdit($event.detail)"
        @supplier-confirm.window="openConfirm($event.detail)"
    >
        <div
            class="space-y-6"
            x-data="dataTable({
                url: '{{ route('supplier.index') }}',
                initial: @js($initial),
                filterKeys: ['search', 'status'],
            })"
        >
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\Supplier::class)
                    <button type="button" class="btn btn-primary" @click="$dispatch('supplier-create')">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Supplier
                    </button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('supplier.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <input type="search" name="search" x-model="filters.search" @input.debounce.300ms="applyFilter()" placeholder="Cari nama, email, NPWP…" class="form-input">
                </div>

                <select name="status" x-model="filters.status" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Aktif + nonaktif</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
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
                                <button type="button" @click="setSort('nama')" class="uppercase tracking-wide hover:underline">
                                    Nama <span x-show="sort === 'nama'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">Kontak</th>
                            <th class="px-5 py-3 font-medium">NPWP</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100" x-text="row.nama"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="row.email || '—'"></p>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                    <p class="text-sm" x-text="row.telepon || '—'"></p>
                                    <p class="max-w-56 truncate text-xs text-slate-500 dark:text-slate-400" x-text="row.alamat || '—'"></p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300" x-text="row.npwp || '—'"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.status ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                        x-text="row.status ? 'Aktif' : 'Nonaktif'"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if (auth()->user()->hasPermissionTo('update_suppliers'))
                                            <button type="button" class="btn-icon" title="Ubah"
                                                @click="$dispatch('supplier-edit', { id: row.id, nama: row.nama, alamat: row.alamat, telepon: row.telepon, email: row.email, npwp: row.npwp, status: row.status ? 'aktif' : 'nonaktif' })">
                                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                                            </button>
                                            <button type="button" class="btn-icon btn-icon-warning" title="Nonaktifkan" x-show="row.status"
                                                @click="$dispatch('supplier-confirm', { title: 'Nonaktifkan supplier', message: 'Supplier tidak bisa dipakai penerimaan baru.', action: '/supplier/' + row.id + '/deactivate', method: 'PATCH', label: 'Nonaktifkan' })">
                                                <x-heroicon-o-eye-slash class="h-4 w-4" />
                                            </button>
                                        @endif
                                        @if (auth()->user()->hasPermissionTo('delete_suppliers'))
                                            <button type="button" class="btn-icon btn-icon-danger" title="Hapus permanen"
                                                @click="$dispatch('supplier-confirm', { title: 'Hapus supplier permanen', message: 'Ditolak bila supplier masih punya riwayat penerimaan.', action: '/supplier/' + row.id, method: 'DELETE', label: 'Hapus' })">
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

        <x-ui.modal name="form" title="Data Supplier" :wide="true">
            <form :action="editing ? '/supplier/' + editing : '{{ route('supplier.store') }}'" method="POST" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                <input type="hidden" name="editing_id" :value="editing">

                <div class="sm:col-span-2">
                    <label for="nama" class="form-label">Nama supplier *</label>
                    <input id="nama" name="nama" type="text" x-model="form.nama" required class="form-input">
                    @error('nama')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="form-label">Email</label>
                    <input id="email" name="email" type="email" x-model="form.email" class="form-input">
                    @error('email')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="telepon" class="form-label">Telepon</label>
                    <input id="telepon" name="telepon" type="text" x-model="form.telepon" class="form-input">
                </div>

                <div class="sm:col-span-2">
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea id="alamat" name="alamat" rows="2" x-model="form.alamat" class="form-textarea"></textarea>
                </div>

                <div>
                    <label for="npwp" class="form-label">NPWP</label>
                    <input id="npwp" name="npwp" type="text" x-model="form.npwp" class="form-input">
                </div>

                <div>
                    <label for="status" class="form-label">Status *</label>
                    <select id="status" name="status" x-model="form.status" required data-tomselect-search="false" class="form-select">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn btn-ghost" @click="formOpen = false">Batal</button>
                    <button type="submit" class="btn btn-primary" x-text="editing ? 'Simpan perubahan' : 'Tambah supplier'"></button>
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
