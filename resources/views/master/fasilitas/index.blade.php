@extends('layouts.app')

@section('title', 'Fasilitas Kesehatan')
@section('subtitle', 'Puskesmas dan pustu')

@section('content')
    @php
        $formDefaults = [
            'kode_faskes' => '', 'nama' => '', 'tipe' => '', 'puskesmas_induk_id' => '',
            'alamat' => '', 'pic' => '', 'kontak_pic' => '', 'telepon' => '',
            'kepala_faskes' => '', 'status' => 'aktif',
        ];
        $hasErrors = $errors->any();
        $openImport = false;
        $openForm = $hasErrors;
    @endphp

    <div
        class="space-y-6"
        x-data="modal(@js(array_merge($formDefaults, old() ? collect(old())->only(array_keys($formDefaults))->toArray() : [])))"
        x-init="editing = @js(old('editing_id')); formOpen = @js($openForm)"
        @faskes-create.window="openCreate()"
        @faskes-edit.window="openEdit($event.detail)"
        @faskes-confirm.window="openConfirm($event.detail)"
    >
        <div
            class="space-y-6"
            x-data="dataTable({
                url: '{{ route('fasilitas.index') }}',
                initial: @js($initial),
                filterKeys: ['search', 'tipe', 'status'],
            })"
        >
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\FasilitasKesehatan::class)
                    <button type="button" class="btn btn-primary" @click="$dispatch('faskes-create')">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Faskes
                    </button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('fasilitas.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <input
                        type="search"
                        name="search"
                        x-model="filters.search" @input.debounce.300ms="applyFilter()"
                        placeholder="Cari kode, nama, PIC…"
                        class="form-input"
                    >
                </div>

                <select name="tipe" x-model="filters.tipe" @change="applyFilter()" class="form-select">
                    <option value="">Semua tipe</option>
                    <option value="puskesmas">Puskesmas</option>
                    <option value="pustu">Pustu</option>
                </select>

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
                                <button type="button" @click="setSort('kode_faskes')" class="uppercase tracking-wide hover:underline">
                                    Kode <span x-show="sort === 'kode_faskes'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">
                                <button type="button" @click="setSort('nama')" class="uppercase tracking-wide hover:underline">
                                    Nama <span x-show="sort === 'nama'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">Tipe</th>
                            <th class="px-5 py-3 font-medium">Induk / Kontak</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300" x-text="row.kode_faskes"></td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100" x-text="row.nama"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="row.kepala_faskes || '—'"></p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.tipe === 'puskesmas' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'"
                                        x-text="row.tipe === 'puskesmas' ? 'Puskesmas' : 'Pustu'"></span>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                    <p class="text-sm" x-text="row.puskesmas_induk ? row.puskesmas_induk.nama : (row.tipe === 'puskesmas' ? '—' : 'Tanpa induk')"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="(row.pic || '—') + (row.telepon ? ' · ' + row.telepon : '')"></p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.status === 'aktif' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                        x-text="row.status === 'aktif' ? 'Aktif' : 'Nonaktif'"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if (auth()->user()->hasPermissionTo('update_fasilitas_kesehatan'))
                                            <button type="button" class="btn-icon" title="Ubah" @click="$dispatch('faskes-edit', row)">
                                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                                            </button>
                                            <button type="button" class="btn-icon btn-icon-warning" title="Nonaktifkan" x-show="row.status === 'aktif'"
                                                @click="$dispatch('faskes-confirm', { title: 'Nonaktifkan faskes', message: 'Faskes ' + row.kode_faskes + ' tidak bisa dipakai transaksi baru.', action: '/fasilitas/' + row.id + '/deactivate', method: 'PATCH', label: 'Nonaktifkan' })">
                                                <x-heroicon-o-eye-slash class="h-4 w-4" />
                                            </button>
                                        @endif
                                        @if (auth()->user()->hasPermissionTo('delete_fasilitas_kesehatan'))
                                            <button type="button" class="btn-icon btn-icon-danger" title="Hapus permanen"
                                                @click="$dispatch('faskes-confirm', { title: 'Hapus faskes permanen', message: 'Ditolak bila faskes masih dipakai user, stok, pustu turunan, atau transaksi.', action: '/fasilitas/' + row.id, method: 'DELETE', label: 'Hapus' })">
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada data yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ubah kata kunci atau atur ulang filter.</p>
                            </td>
                        </tr>
                        <tr x-show="loading">
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">Memuat…</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <x-ui.table-pagination />
        </section>
        </div>

        <x-ui.modal name="form" title="Data Faskes" :wide="true">
            <form :action="editing ? '/fasilitas/' + editing : '{{ route('fasilitas.store') }}'" method="POST" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                <input type="hidden" name="editing_id" :value="editing">

                <div>
                    <label for="kode_faskes" class="form-label">Kode faskes *</label>
                    <input id="kode_faskes" name="kode_faskes" type="text" x-model="form.kode_faskes" required class="form-input">
                    @error('kode_faskes')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="tipe" class="form-label">Tipe *</label>
                    <select id="tipe" name="tipe" x-model="form.tipe" required class="form-select">
                        <option value="">— Pilih —</option>
                        <option value="puskesmas">Puskesmas</option>
                        <option value="pustu">Pustu</option>
                    </select>
                    @error('tipe')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="nama" class="form-label">Nama fasilitas *</label>
                    <input id="nama" name="nama" type="text" x-model="form.nama" required class="form-input">
                    @error('nama')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2" x-show="form.tipe === 'pustu'" x-cloak>
                    <label for="puskesmas_induk_id" class="form-label">Puskesmas induk *</label>
                    <select id="puskesmas_induk_id" name="puskesmas_induk_id" x-model="form.puskesmas_induk_id" class="form-select">
                        <option value="">— Pilih puskesmas —</option>
                        @foreach ($puskesmasList as $p)
                            <option value="{{ $p->id }}">{{ $p->nama }}</option>
                        @endforeach
                    </select>
                    @error('puskesmas_induk_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="alamat" class="form-label">Alamat *</label>
                    <textarea id="alamat" name="alamat" rows="2" x-model="form.alamat" required class="form-textarea"></textarea>
                    @error('alamat')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="pic" class="form-label">PIC</label>
                    <input id="pic" name="pic" type="text" x-model="form.pic" class="form-input">
                </div>

                <div>
                    <label for="kontak_pic" class="form-label">Kontak PIC</label>
                    <input id="kontak_pic" name="kontak_pic" type="text" x-model="form.kontak_pic" class="form-input">
                </div>

                <div>
                    <label for="telepon" class="form-label">Telepon</label>
                    <input id="telepon" name="telepon" type="text" x-model="form.telepon" class="form-input">
                </div>

                <div>
                    <label for="kepala_faskes" class="form-label">Kepala faskes</label>
                    <input id="kepala_faskes" name="kepala_faskes" type="text" x-model="form.kepala_faskes" class="form-input">
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
                    <button type="submit" class="btn btn-primary" x-text="editing ? 'Simpan perubahan' : 'Tambah faskes'"></button>
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
