@extends('layouts.app')

@section('title', 'Obat')
@section('subtitle', 'Master data obat')

@section('content')
    @php
        $bentukLabels = [
            'tablet' => 'Tablet', 'kapsul' => 'Kapsul', 'sirup' => 'Sirup', 'salep' => 'Salep',
            'injeksi' => 'Injeksi', 'drop' => 'Drop', 'inhaler' => 'Inhaler', 'suppositoria' => 'Suppositoria',
        ];
        $venLabels = ['V' => 'Vital', 'E' => 'Esensial', 'N' => 'Non-Esensial'];
        $formDefaults = [
            'kode_obat' => '', 'nama_obat' => '', 'nama_generik' => '', 'kategori' => '',
            'satuan' => '', 'kekuatan' => '', 'bentuk_sediaan' => '', 'produsen' => '',
            'kemasan' => '', 'harga_satuan' => '', 'status' => 'aktif',
            'ven_kategori' => '', 'metode_stok' => 'fefo',
        ];
        $hasErrors = $errors->any();
        $openImport = $errors->has('file');
        $openForm = $hasErrors && ! $openImport;
    @endphp

    <div
        class="space-y-6"
        x-data="modal(@js(array_merge($formDefaults, old() ? collect(old())->only(array_keys($formDefaults))->toArray() : [])))"
        x-init="editing = @js(old('editing_id')); formOpen = @js($openForm); importOpen = @js($openImport)"
        @obat-create.window="openCreate()"
        @obat-import.window="importOpen = true"
        @obat-edit.window="openEdit($event.detail)"
        @obat-confirm.window="openConfirm($event.detail)"
    >
        <div
            class="space-y-6"
            x-data="dataTable({
                url: '{{ route('obat.index') }}',
                initial: @js($initial),
                filterKeys: ['search', 'kategori', 'bentuk', 'ven', 'status', 'metode'],
                extra: { bentukLabels: @js($bentukLabels), venLabels: @js($venLabels) },
            })"
        >
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\Obat::class)
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="$dispatch('obat-create')"
                    >
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Obat
                    </button>
                    <button
                        type="button"
                        class="btn btn-secondary"
                        @click="$dispatch('obat-import')"
                    >
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                        Import CSV
                    </button>
                @endcan
                <a
                    :href="exportUrl('{{ route('obat.export') }}')"
                    class="btn btn-secondary"
                >
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                    Export Excel
                </a>
            </div>
        </section>

        @if ($importStatus ?? false)
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @if (empty($importStatus['completed_at']))
                    <p class="text-amber-700 dark:text-amber-300">
                        Import terakhir ({{ $importStatus['file_name'] }}) masih berjalan:
                        {{ $importStatus['successful_rows'] }}/{{ $importStatus['total_rows'] ?: '…' }} baris.
                        Refresh halaman untuk memperbarui.
                    </p>
                @else
                    <p class="text-slate-600 dark:text-slate-300">
                        Import terakhir ({{ $importStatus['file_name'] }}):
                        {{ number_format($importStatus['successful_rows'], 0, ',', '.') }} dari
                        {{ number_format($importStatus['processed_rows'], 0, ',', '.') }} baris berhasil.
                    </p>
                @endif
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('obat.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-7">
                <div class="lg:col-span-2">
                    <input
                        type="search"
                        name="search"
                        x-model="filters.search" @input.debounce.300ms="applyFilter()"
                        placeholder="Cari kode, nama, generik, kategori…"
                        class="form-input"
                    >
                </div>

                <select name="kategori" x-model="filters.kategori" @change="applyFilter()" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($kategoriList as $kategori)
                        <option value="{{ $kategori }}">{{ $kategori }}</option>
                    @endforeach
                </select>

                <select name="bentuk" x-model="filters.bentuk" @change="applyFilter()" class="form-select">
                    <option value="">Semua bentuk</option>
                    @foreach ($bentukLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select name="ven" x-model="filters.ven" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua VEN</option>
                    @foreach ($venLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select name="status" x-model="filters.status" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Aktif + nonaktif</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>

                <select name="metode" x-model="filters.metode" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua metode</option>
                    <option value="fefo">FEFO</option>
                    <option value="fifo">FIFO</option>
                    <option value="lifo">LIFO</option>
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-7">
                    <button type="submit" class="btn btn-dark">
                        Terapkan
                    </button>
                    <button type="button" class="btn btn-ghost" @click="reset()">
                        Atur ulang
                    </button>
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
                                <button type="button" @click="setSort('kode_obat')" class="uppercase tracking-wide hover:underline">
                                    Kode <span x-show="sort === 'kode_obat'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">
                                <button type="button" @click="setSort('nama_obat')" class="uppercase tracking-wide hover:underline">
                                    Nama <span x-show="sort === 'nama_obat'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">Kategori</th>
                            <th class="px-5 py-3 font-medium">Bentuk</th>
                            <th class="px-5 py-3 font-medium">VEN</th>
                            <th class="px-5 py-3 text-right font-medium">
                                <button type="button" @click="setSort('harga_satuan')" class="uppercase tracking-wide hover:underline">
                                    Harga <span x-show="sort === 'harga_satuan'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Metode</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300" x-text="row.kode_obat"></td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100" x-text="row.nama_obat"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="(row.nama_generik || '—') + ' · ' + row.satuan + (row.kekuatan ? ' ' + row.kekuatan : '')"></p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.kategori"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="bentukLabels[row.bentuk_sediaan] || row.bentuk_sediaan"></td>
                                <td class="px-5 py-3">
                                    <span x-show="row.ven_kategori" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.ven_kategori === 'V' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : (row.ven_kategori === 'E' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300')"
                                        x-text="row.ven_kategori"></span>
                                    <span x-show="! row.ven_kategori" class="text-slate-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100" x-text="row.harga_satuan ? fmtRupiah(row.harga_satuan) : '—'"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.status === 'aktif' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                        x-text="row.status === 'aktif' ? 'Aktif' : 'Nonaktif'"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300" x-text="String(row.metode_stok).toUpperCase()"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if (auth()->user()->hasPermissionTo('update_obat'))
                                            <button type="button" class="btn-icon" title="Ubah" @click="$dispatch('obat-edit', row)">
                                                <x-heroicon-o-pencil-square class="h-4 w-4" />
                                            </button>
                                            <button type="button" class="btn-icon btn-icon-warning" title="Nonaktifkan" x-show="row.status === 'aktif'"
                                                @click="$dispatch('obat-confirm', { title: 'Nonaktifkan obat', message: 'Obat ' + row.kode_obat + ' tidak lagi bisa dipakai transaksi baru. Riwayat batch tetap utuh.', action: '/obat/' + row.id + '/deactivate', method: 'PATCH', label: 'Nonaktifkan' })">
                                                <x-heroicon-o-eye-slash class="h-4 w-4" />
                                            </button>
                                        @endif
                                        @if (auth()->user()->hasPermissionTo('delete_obat'))
                                            <button type="button" class="btn-icon btn-icon-danger" title="Hapus permanen"
                                                @click="$dispatch('obat-confirm', { title: 'Hapus obat permanen', message: 'Ditolak bila obat masih punya batch.', action: '/obat/' + row.id, method: 'DELETE', label: 'Hapus' })">
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="9" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada data yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ubah kata kunci atau atur ulang filter.</p>
                            </td>
                        </tr>
                        <tr x-show="loading">
                            <td colspan="9" class="px-5 py-12 text-center text-sm text-slate-500">Memuat…</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <x-ui.table-pagination />
        </section>
        </div>

        {{-- Modal create / edit --}}
        <x-ui.modal name="form" title="Data Obat" :wide="true">
            <form :action="editing ? '/obat/' + editing : '{{ route('obat.store') }}'" method="POST" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                <input type="hidden" name="editing_id" :value="editing">

                <div>
                    <label for="kode_obat" class="form-label">Kode obat *</label>
                    <input id="kode_obat" name="kode_obat" type="text" x-model="form.kode_obat" required
                        class="form-input">
                    @error('kode_obat')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kategori" class="form-label">Kategori *</label>
                    <input id="kategori" name="kategori" type="text" list="kategori-list" x-model="form.kategori" required
                        class="form-input">
                    <datalist id="kategori-list">
                        @foreach ($kategoriList as $kategori)
                            <option value="{{ $kategori }}"></option>
                        @endforeach
                    </datalist>
                    @error('kategori')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="nama_obat" class="form-label">Nama obat *</label>
                    <input id="nama_obat" name="nama_obat" type="text" x-model="form.nama_obat" required
                        class="form-input">
                    @error('nama_obat')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="nama_generik" class="form-label">Nama generik</label>
                    <input id="nama_generik" name="nama_generik" type="text" x-model="form.nama_generik"
                        class="form-input">
                </div>

                <div>
                    <label for="satuan" class="form-label">Satuan *</label>
                    <input id="satuan" name="satuan" type="text" x-model="form.satuan" required
                        class="form-input">
                    @error('satuan')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kekuatan" class="form-label">Kekuatan</label>
                    <input id="kekuatan" name="kekuatan" type="text" x-model="form.kekuatan" placeholder="500 mg"
                        class="form-input">
                </div>

                <div>
                    <label for="bentuk_sediaan" class="form-label">Bentuk sediaan *</label>
                    <select id="bentuk_sediaan" name="bentuk_sediaan" x-model="form.bentuk_sediaan" required
                        class="form-select">
                        <option value="">— Pilih —</option>
                        @foreach ($bentukLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('bentuk_sediaan')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="ven_kategori" class="form-label">VEN</label>
                    <select id="ven_kategori" name="ven_kategori" x-model="form.ven_kategori" data-tomselect-search="false"
                        class="form-select">
                        <option value="">—</option>
                        @foreach ($venLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="produsen" class="form-label">Produsen</label>
                    <input id="produsen" name="produsen" type="text" x-model="form.produsen"
                        class="form-input">
                </div>

                <div>
                    <label for="kemasan" class="form-label">Kemasan</label>
                    <input id="kemasan" name="kemasan" type="text" x-model="form.kemasan"
                        class="form-input">
                </div>

                <div>
                    <label for="harga_satuan" class="form-label">Harga satuan (Rp)</label>
                    <input id="harga_satuan" name="harga_satuan" type="number" min="0" step="0.01" x-model="form.harga_satuan"
                        class="form-input">
                    @error('harga_satuan')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="metode_stok" class="form-label">Metode stok *</label>
                    <select id="metode_stok" name="metode_stok" x-model="form.metode_stok" required data-tomselect-search="false"
                        class="form-select">
                        <option value="fefo">FEFO</option>
                        <option value="fifo">FIFO</option>
                        <option value="lifo">LIFO</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="form-label">Status *</label>
                    <select id="status" name="status" x-model="form.status" required data-tomselect-search="false"
                        class="form-select">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn btn-ghost" @click="formOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary" x-text="editing ? 'Simpan perubahan' : 'Tambah obat'"></button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Modal konfirmasi --}}
        <x-ui.modal name="confirm">
            <form :action="confirm.action" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="confirm.method ?? 'DELETE'">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="confirm.title"></h2>
                <p class="text-sm text-slate-600 dark:text-slate-300" x-text="confirm.message"></p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost" @click="confirmOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-danger-solid" x-text="confirm.label ?? 'Hapus'"></button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Modal import --}}
        <x-ui.modal name="import" title="Import Obat dari CSV">
            <form method="POST" action="{{ route('obat.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Unduh <a href="{{ route('obat.import.template') }}" class="font-medium text-brand-600 underline dark:text-brand-300">template CSV</a>
                    terlebih dahulu. Baris dicocokkan by <code>kode_obat</code>: kode baru ditambah, kode lama diperbarui.
                </p>
                <input
                    type="file"
                    name="file"
                    accept=".csv,.txt"
                    required
                    class="form-file"
                >
                @error('file')<p class="form-error">{{ $message }}</p>@enderror
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost" @click="importOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Mulai import
                    </button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
