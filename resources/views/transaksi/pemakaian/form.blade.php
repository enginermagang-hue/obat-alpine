@php
    $isEdit = $pemakaian !== null;
    $header = [
        'tanggal_pemakaian' => old('tanggal_pemakaian', $pemakaian?->tanggal_pemakaian?->format('Y-m-d') ?? now()->format('Y-m-d')),
        'jenis_pelayanan' => old('jenis_pelayanan', $pemakaian->jenis_pelayanan ?? 'rawat_jalan'),
        'nama_pasien' => old('nama_pasien', $pemakaian->nama_pasien ?? ''),
        'no_rekam_medis' => old('no_rekam_medis', $pemakaian->no_rekam_medis ?? ''),
        'diagnosa_kode' => old('diagnosa_kode', $pemakaian->diagnosa_kode ?? ''),
        'catatan' => old('catatan', $pemakaian->catatan ?? ''),
    ];
    $rows = old('rows', $oldRows);
    $layananList = ['rawat_jalan' => 'Rawat Jalan', 'rawat_inap' => 'Rawat Inap', 'uks' => 'UKS', 'posyandu' => 'Posyandu', 'pusling' => 'Pusling', 'gigi' => 'Poli Gigi', 'laboratorium' => 'Laboratorium', 'apotek' => 'Apotek', 'lainnya' => 'Lainnya'];
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah Pemakaian '.$pemakaian->nomor_pemakaian : 'Catat Pemakaian')
@section('subtitle', 'Pemakaian obat mengurangi stok (FEFO/FIFO/LIFO)')

@section('content')
    <div
        class="space-y-6"
        x-data="transactionForm({
            obatUrl: '{{ route('api.obat.search') }}',
            fasilitasId: @js($defaultFaskes),
            initialRows: @js($rows),
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('pemakaian.show', $pemakaian) : route('pemakaian.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali
            </a>
        </section>

        @if ($errors->any())
            <section class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                <p class="font-medium">Periksa kembali isian berikut:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form
            method="POST"
            action="{{ $isEdit ? route('pemakaian.update', $pemakaian) : route('pemakaian.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header pemakaian — {{ $faskesNama }}</h2>
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="tanggal_pemakaian" class="form-label">Tanggal *</label>
                        <input id="tanggal_pemakaian" name="tanggal_pemakaian" type="date" value="{{ $header['tanggal_pemakaian'] }}" required max="{{ now()->format('Y-m-d') }}" class="form-input">
                        @error('tanggal_pemakaian')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="jenis_pelayanan" class="form-label">Jenis pelayanan *</label>
                        <select id="jenis_pelayanan" name="jenis_pelayanan" class="form-select">
                            @foreach ($layananList as $value => $label)
                                <option value="{{ $value }}" @selected($header['jenis_pelayanan'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('jenis_pelayanan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="nama_pasien" class="form-label">Nama pasien</label>
                        <input id="nama_pasien" name="nama_pasien" type="text" value="{{ $header['nama_pasien'] }}" class="form-input">
                    </div>

                    <div>
                        <label for="no_rekam_medis" class="form-label">No. RM</label>
                        <input id="no_rekam_medis" name="no_rekam_medis" type="text" value="{{ $header['no_rekam_medis'] }}" class="form-input">
                    </div>

                    <div>
                        <label for="diagnosa_kode" class="form-label">Kode diagnosa</label>
                        <input id="diagnosa_kode" name="diagnosa_kode" type="text" value="{{ $header['diagnosa_kode'] }}" class="form-input">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ $header['catatan'] }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item pemakaian</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Batch dialokasikan otomatis (FEFO/FIFO/LIFO). Cek di halaman detail.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" @click="addRow()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah baris
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-12">
                            <div class="relative sm:col-span-5">
                                <label class="form-label" :for="'obat-search-' + i">Obat *</label>
                                <input
                                    :id="'obat-search-' + i"
                                    type="text"
                                    placeholder="Ketik untuk mencari…"
                                    :value="row.obat_label"
                                    @input="row.obat_label = $event.target.value; row.obatOpen = true; searchObat(i, $event.target.value)"
                                    @focus="row.obatOpen = true; if (!row.obatResults.length) searchObat(i, '')"
                                    @click.outside="row.obatOpen = false"
                                    class="form-input"
                                    autocomplete="off"
                                >
                                <input type="hidden" :name="'rows[' + i + '][obat_id]'" x-model="row.obat_id">
                                <div x-show="row.obatOpen && row.obatResults.length" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                    <template x-for="item in row.obatResults" :key="item.value">
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="pickObat(i, item)">
                                            <span class="block font-medium text-slate-800 dark:text-slate-100" x-text="item.label"></span>
                                        </button>
                                    </template>
                                </div>
                                <div class="mt-1 flex items-center justify-between">
                                    <span class="text-xs text-slate-400" x-show="row.obatLoading">Mencari…</span>
                                    <button type="button" class="text-xs text-slate-400 hover:text-slate-600" x-show="row.obat_id" @click="clearObat(i)">Ganti</button>
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'jumlah-' + i">Jumlah *</label>
                                <input :id="'jumlah-' + i" type="number" min="1" :name="'rows[' + i + '][jumlah]'" x-model.number="row.jumlah" required class="form-input text-right">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'dosis-' + i">Dosis</label>
                                <input :id="'dosis-' + i" type="text" :name="'rows[' + i + '][dosis]'" x-model="row.dosis" placeholder="3x1" class="form-input">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'satuan-' + i">Satuan dosis</label>
                                <input :id="'satuan-' + i" type="text" :name="'rows[' + i + '][satuan_dosis]'" x-model="row.satuan_dosis" placeholder="tablet" class="form-input">
                            </div>

                            <div class="flex items-end justify-end sm:col-span-1">
                                <button type="button" class="btn-icon btn-icon-danger" title="Hapus baris" @click="removeRow(i)">
                                    <x-heroicon-o-x-mark class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </template>

                    @error('rows')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="flex items-center justify-end gap-2">
                <a href="{{ $isEdit ? route('pemakaian.show', $pemakaian) : route('pemakaian.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan pemakaian</button>
            </section>
        </form>
    </div>
@endsection
