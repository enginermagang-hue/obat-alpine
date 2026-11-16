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
    >
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\FasilitasKesehatan::class)
                    <button type="button" class="btn btn-primary" @click="openCreate()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Faskes
                    </button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('fasilitas.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Cari kode, nama, PIC…"
                        class="form-input"
                    >
                </div>

                <select name="tipe" class="form-select">
                    <option value="">Semua tipe</option>
                    <option value="puskesmas" @selected(($filters['tipe'] ?? '') === 'puskesmas')>Puskesmas</option>
                    <option value="pustu" @selected(($filters['tipe'] ?? '') === 'pustu')>Pustu</option>
                </select>

                <select name="status" data-tomselect-search="false" class="form-select">
                    <option value="">Aktif + nonaktif</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a href="{{ route('fasilitas.index') }}" class="btn btn-ghost">Atur ulang</a>
                </div>
            </form>

            @if ($faskes->isEmpty())
                <x-ui.empty-state title="Belum ada fasilitas" hint="Tambahkan puskesmas atau pustu." icon="building-office-2" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Kode</th>
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Tipe</th>
                                <th class="px-5 py-3 font-medium">Induk / Kontak</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($faskes as $row)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row->kode_faskes }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $row->nama }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row->kepala_faskes ?? '—' }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$row->tipe === 'puskesmas' ? 'brand' : 'violet'">{{ ucfirst($row->tipe) }}</x-ui.badge>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                        <p class="text-sm">{{ $row->puskesmasInduk?->nama ?? ($row->tipe === 'puskesmas' ? '—' : 'Tanpa induk') }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row->pic ?? '—' }}{{ $row->telepon ? ' · '.$row->telepon : '' }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$row->status === 'aktif' ? 'emerald' : 'slate'">{{ $row->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @can('update', $row)
                                                <button type="button" class="btn-icon" title="Ubah"
                                                    @click="openEdit(@js($row->only(['id', 'kode_faskes', 'nama', 'tipe', 'puskesmas_induk_id', 'alamat', 'pic', 'kontak_pic', 'telepon', 'kepala_faskes', 'status'])))">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                </button>
                                                @if ($row->status === 'aktif')
                                                    <button type="button" class="btn-icon btn-icon-warning" title="Nonaktifkan"
                                                        @click="openConfirm({ title: 'Nonaktifkan faskes', message: 'Faskes {{ $row->kode_faskes }} tidak bisa dipakai transaksi baru.', action: '{{ route('fasilitas.deactivate', $row) }}', method: 'PATCH', label: 'Nonaktifkan' })">
                                                        <x-heroicon-o-eye-slash class="h-4 w-4" />
                                                    </button>
                                                @endif
                                            @endcan
                                            @can('delete', $row)
                                                <button type="button" class="btn-icon btn-icon-danger" title="Hapus permanen"
                                                    @click="openConfirm({ title: 'Hapus faskes permanen', message: 'Ditolak bila faskes masih dipakai user, stok, pustu turunan, atau transaksi.', action: '{{ route('fasilitas.destroy', $row) }}', method: 'DELETE', label: 'Hapus' })">
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

                <x-ui.pagination :paginator="$faskes" />
            @endif
        </section>

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
