@extends('layouts.app')

@section('title', 'Sumber Dana')
@section('subtitle', 'Anggaran per tahun')

@section('content')
    @php
        $formDefaults = [
            'kode' => '', 'nama' => '', 'tahun' => '', 'total_anggaran' => '',
            'status' => 'aktif', 'keterangan' => '',
        ];
        $openForm = $errors->any();
    @endphp

    <div
        class="space-y-6"
        x-data="modal(@js(array_merge($formDefaults, old() ? collect(old())->only(array_keys($formDefaults))->toArray() : [])))"
        x-init="editing = @js(old('editing_id')); formOpen = @js($openForm)"
    >
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\SumberDana::class)
                    <button type="button" class="btn btn-primary" @click="openCreate()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah Sumber Dana
                    </button>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('sumber-dana.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari kode atau nama…" class="form-input">
                </div>

                <select name="tahun" class="form-select">
                    <option value="">Semua tahun</option>
                    @foreach ($tahunList as $tahun)
                        <option value="{{ $tahun }}" @selected((string) ($filters['tahun'] ?? '') === (string) $tahun)>{{ $tahun }}</option>
                    @endforeach
                </select>

                <select name="status" data-tomselect-search="false" class="form-select">
                    <option value="">Aktif + nonaktif</option>
                    <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a href="{{ route('sumber-dana.index') }}" class="btn btn-ghost">Atur ulang</a>
                </div>
            </form>

            @if ($dana->isEmpty())
                <x-ui.empty-state title="Belum ada sumber dana" hint="Tambahkan anggaran per tahun." icon="banknotes" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Kode</th>
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Tahun</th>
                                <th class="px-5 py-3 text-right font-medium">Pagu</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($dana as $row)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row->kode }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $row->nama }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row->keterangan ?? '—' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->tahun }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">Rp {{ number_format((float) $row->total_anggaran, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$row->status ? 'emerald' : 'slate'">{{ $row->status ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @can('update', $row)
                                                <button type="button" class="btn-icon" title="Ubah"
                                                    @click="openEdit(@js(array_merge($row->only(['id', 'kode', 'nama', 'tahun', 'status', 'keterangan']), ['total_anggaran' => (string) $row->total_anggaran, 'status' => $row->getAttributes()['status']])))">
                                                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                                                </button>
                                                @if ($row->status)
                                                    <button type="button" class="btn-icon btn-icon-warning" title="Nonaktifkan"
                                                        @click="openConfirm({ title: 'Nonaktifkan sumber dana', message: 'Sumber dana {{ $row->kode }} tahun {{ $row->tahun }} tidak bisa dipakai transaksi baru.', action: '{{ route('sumber-dana.deactivate', $row) }}', method: 'PATCH', label: 'Nonaktifkan' })">
                                                        <x-heroicon-o-eye-slash class="h-4 w-4" />
                                                    </button>
                                                @endif
                                            @endcan
                                            @can('delete', $row)
                                                <button type="button" class="btn-icon btn-icon-danger" title="Hapus permanen"
                                                    @click="openConfirm({ title: 'Hapus sumber dana permanen', message: 'Ditolak bila masih dipakai batch, penerimaan, RKO, atau alokasi dana.', action: '{{ route('sumber-dana.destroy', $row) }}', method: 'DELETE', label: 'Hapus' })">
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

                <x-ui.pagination :paginator="$dana" />
            @endif
        </section>

        <x-ui.modal name="form" title="Data Sumber Dana" :wide="true">
            <form :action="editing ? '/sumber-dana/' + editing : '{{ route('sumber-dana.store') }}'" method="POST" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="_method" :value="editing ? 'PUT' : 'POST'">
                <input type="hidden" name="editing_id" :value="editing">

                <div>
                    <label for="kode" class="form-label">Kode *</label>
                    <input id="kode" name="kode" type="text" x-model="form.kode" required class="form-input">
                    @error('kode')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="tahun" class="form-label">Tahun *</label>
                    <input id="tahun" name="tahun" type="number" min="2000" max="2100" x-model="form.tahun" required class="form-input">
                    @error('tahun')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="nama" class="form-label">Nama sumber dana *</label>
                    <input id="nama" name="nama" type="text" x-model="form.nama" required class="form-input">
                    @error('nama')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="total_anggaran" class="form-label">Total anggaran (Rp) *</label>
                    <input id="total_anggaran" name="total_anggaran" type="number" min="0" step="0.01" x-model="form.total_anggaran" required class="form-input">
                    @error('total_anggaran')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="status" class="form-label">Status *</label>
                    <select id="status" name="status" x-model="form.status" required data-tomselect-search="false" class="form-select">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="keterangan" class="form-label">Keterangan</label>
                    <textarea id="keterangan" name="keterangan" rows="2" x-model="form.keterangan" class="form-textarea"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn btn-ghost" @click="formOpen = false">Batal</button>
                    <button type="submit" class="btn btn-primary" x-text="editing ? 'Simpan perubahan' : 'Tambah sumber dana'"></button>
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
