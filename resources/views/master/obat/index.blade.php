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
    >
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\Obat::class)
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                        @click="openCreate()"
                    >
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Obat
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        @click="importOpen = true"
                    >
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                        Import CSV
                    </button>
                @endcan
                <a
                    href="{{ route('obat.export', request()->query()) }}"
                    class="flex items-center gap-1.5 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
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
            <form method="GET" action="{{ route('obat.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-7">
                <div class="lg:col-span-2">
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Cari kode, nama, generik, kategori…"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-brand-500/20"
                    >
                </div>

                <select name="kategori" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Semua kategori</option>
                    @foreach ($kategoriList as $kategori)
                        <option value="{{ $kategori }}" @selected(($filters['kategori'] ?? '') === $kategori)>{{ $kategori }}</option>
                    @endforeach
                </select>

                <select name="bentuk" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Semua bentuk</option>
                    @foreach ($bentukLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['bentuk'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="ven" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Semua VEN</option>
                    @foreach ($venLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['ven'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="status" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Aktif + nonaktif</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>

                <select name="metode" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Semua metode</option>
                    <option value="fefo" @selected(($filters['metode'] ?? '') === 'fefo')>FEFO</option>
                    <option value="fifo" @selected(($filters['metode'] ?? '') === 'fifo')>FIFO</option>
                    <option value="lifo" @selected(($filters['metode'] ?? '') === 'lifo')>LIFO</option>
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-7">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                        Terapkan
                    </button>
                    <a href="{{ route('obat.index') }}" class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                        Atur ulang
                    </a>
                </div>
            </form>

            @if ($obat->isEmpty())
                <x-ui.empty-state title="Belum ada data obat" hint="Tambahkan manual atau import dari CSV." icon="beaker" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Kode</th>
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Kategori</th>
                                <th class="px-5 py-3 font-medium">Bentuk</th>
                                <th class="px-5 py-3 font-medium">VEN</th>
                                <th class="px-5 py-3 text-right font-medium">Harga</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Metode</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($obat as $row)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row->kode_obat }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $row->nama_obat }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row->nama_generik ?? '—' }} &middot; {{ $row->satuan }}{{ $row->kekuatan ? ' '.$row->kekuatan : '' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->kategori }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $bentukLabels[$row->bentuk_sediaan] ?? $row->bentuk_sediaan }}</td>
                                    <td class="px-5 py-3">
                                        @if ($row->ven_kategori)
                                            <x-ui.badge :tone="$row->ven_kategori === 'V' ? 'red' : ($row->ven_kategori === 'E' ? 'emerald' : 'slate')">{{ $row->ven_kategori }}</x-ui.badge>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ $row->harga_satuan ? 'Rp '.number_format($row->harga_satuan, 0, ',', '.') : '—' }}</td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$row->status === 'aktif' ? 'emerald' : 'slate'">{{ $row->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge tone="brand">{{ strtoupper($row->metode_stok instanceof \App\Enums\MetodeStok ? $row->metode_stok->value : $row->metode_stok) }}</x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @can('update', $row)
                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                                    title="Ubah"
                                                    @click="openEdit(@js($row->only(['id', 'kode_obat', 'nama_obat', 'nama_generik', 'kategori', 'satuan', 'kekuatan', 'bentuk_sediaan', 'produsen', 'kemasan', 'harga_satuan', 'status', 'ven_kategori', 'metode_stok'])))"
                                                >
                                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                </button>
                                                @if ($row->status === 'aktif')
                                                    <button
                                                        type="button"
                                                        class="rounded-lg p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10"
                                                        title="Nonaktifkan"
                                                        @click="openConfirm({ title: 'Nonaktifkan obat', message: 'Obat {{ $row->kode_obat }} tidak lagi bisa dipakai transaksi baru. Riwayat batch tetap utuh.', action: '{{ route('obat.deactivate', $row) }}', method: 'PATCH', label: 'Nonaktifkan' })"
                                                    >
                                                        <x-heroicon-o-eye-slash class="h-4 w-4" />
                                                    </button>
                                                @endif
                                            @endcan
                                            @can('delete', $row)
                                                <button
                                                    type="button"
                                                    class="rounded-lg p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10"
                                                    title="Hapus permanen"
                                                    @click="openConfirm({ title: 'Hapus obat permanen', message: 'Ketik konfirmasi lalu tekan Hapus. Ditolak bila obat masih punya batch.', action: '{{ route('obat.destroy', $row) }}', method: 'DELETE', label: 'Hapus' })"
                                                >
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

                <x-ui.pagination :paginator="$obat" />
            @endif
        </section>

        {{-- Modal create / edit --}}
        <x-ui.modal name="form" title="Data Obat" :wide="true">
            <form :action="editing ? '/obat/' + editing : '{{ route('obat.store') }}'" method="POST" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                <input type="hidden" name="editing_id" :value="editing">

                <div>
                    <label for="kode_obat" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Kode obat *</label>
                    <input id="kode_obat" name="kode_obat" type="text" x-model="form.kode_obat" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @error('kode_obat')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kategori" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Kategori *</label>
                    <input id="kategori" name="kategori" type="text" list="kategori-list" x-model="form.kategori" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <datalist id="kategori-list">
                        @foreach ($kategoriList as $kategori)
                            <option value="{{ $kategori }}"></option>
                        @endforeach
                    </datalist>
                    @error('kategori')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="nama_obat" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Nama obat *</label>
                    <input id="nama_obat" name="nama_obat" type="text" x-model="form.nama_obat" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @error('nama_obat')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="nama_generik" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Nama generik</label>
                    <input id="nama_generik" name="nama_generik" type="text" x-model="form.nama_generik"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>

                <div>
                    <label for="satuan" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Satuan *</label>
                    <input id="satuan" name="satuan" type="text" x-model="form.satuan" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @error('satuan')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kekuatan" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Kekuatan</label>
                    <input id="kekuatan" name="kekuatan" type="text" x-model="form.kekuatan" placeholder="500 mg"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>

                <div>
                    <label for="bentuk_sediaan" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Bentuk sediaan *</label>
                    <select id="bentuk_sediaan" name="bentuk_sediaan" x-model="form.bentuk_sediaan" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">— Pilih —</option>
                        @foreach ($bentukLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('bentuk_sediaan')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="ven_kategori" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">VEN</label>
                    <select id="ven_kategori" name="ven_kategori" x-model="form.ven_kategori"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">—</option>
                        @foreach ($venLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="produsen" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Produsen</label>
                    <input id="produsen" name="produsen" type="text" x-model="form.produsen"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>

                <div>
                    <label for="kemasan" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Kemasan</label>
                    <input id="kemasan" name="kemasan" type="text" x-model="form.kemasan"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>

                <div>
                    <label for="harga_satuan" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Harga satuan (Rp)</label>
                    <input id="harga_satuan" name="harga_satuan" type="number" min="0" step="0.01" x-model="form.harga_satuan"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @error('harga_satuan')<p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="metode_stok" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Metode stok *</label>
                    <select id="metode_stok" name="metode_stok" x-model="form.metode_stok" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="fefo">FEFO</option>
                        <option value="fifo">FIFO</option>
                        <option value="lifo">LIFO</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Status *</label>
                    <select id="status" name="status" x-model="form.status" required
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 sm:col-span-2">
                    <button type="button" class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="formOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700" x-text="editing ? 'Simpan perubahan' : 'Tambah obat'"></button>
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
                    <button type="button" class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="confirmOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700" x-text="confirm.label ?? 'Hapus'"></button>
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
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                >
                @error('file')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="rounded-xl px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="importOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        Mulai import
                    </button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
