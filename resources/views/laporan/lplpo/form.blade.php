@php
    $isEdit = $laporan !== null;
    $rows = old('rows', $oldRows);
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah LPLPO '.$laporan->nomor_laporan : 'LPLPO Baru')
@section('subtitle', 'Detail dihitung dari riwayat stok saat simpan')

@section('content')
    <div
        class="space-y-6"
        x-data="transactionForm({
            obatUrl: '{{ route('api.obat.search') }}',
            initialRows: @js($rows),
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('lplpo.show', $laporan) : route('lplpo.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
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
            action="{{ $isEdit ? route('lplpo.update', $laporan) : route('lplpo.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header LPLPO — {{ $faskesNama }}</h2>
                    @unless ($isEdit)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Detail otomatis digenerate dari riwayat stok periode ini. Kosongkan baris bila ingin generate otomatis.</p>
                    @endunless
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="periode_bulan" class="form-label">Bulan *</label>
                        <select id="periode_bulan" name="periode_bulan" class="form-select">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) old('periode_bulan', $laporan->periode_bulan ?? now()->month) === $m)>{{ \Carbon\Carbon::create(null, $m)->translatedFormat('F') }}</option>
                            @endfor
                        </select>
                        @error('periode_bulan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="periode_tahun" class="form-label">Tahun *</label>
                        <input id="periode_tahun" name="periode_tahun" type="number" min="2000" max="2100" value="{{ old('periode_tahun', $laporan->periode_tahun ?? now()->year) }}" required class="form-input">
                        @error('periode_tahun')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tanggal_pembuatan" class="form-label">Tanggal pembuatan *</label>
                        <input id="tanggal_pembuatan" name="tanggal_pembuatan" type="date" value="{{ old('tanggal_pembuatan', $laporan?->tanggal_pembuatan?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="form-input">
                        @error('tanggal_pembuatan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-1">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ old('catatan', $laporan->catatan ?? '') }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item LPLPO</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Sisa stok dihitung otomatis (awal + masuk − keluar).</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" @click="addRow()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah baris
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-12">
                            <div class="relative sm:col-span-4">
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
                                <label class="form-label" :for="'awal-' + i">Stok awal</label>
                                <input :id="'awal-' + i" type="number" min="0" :name="'rows[' + i + '][stok_awal]'" x-model.number="row.stok_awal" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'masuk-' + i">Masuk</label>
                                <input :id="'masuk-' + i" type="number" min="0" :name="'rows[' + i + '][jumlah_masuk]'" x-model.number="row.jumlah_masuk" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'keluar-' + i">Keluar</label>
                                <input :id="'keluar-' + i" type="number" min="0" :name="'rows[' + i + '][jumlah_keluar]'" x-model.number="row.jumlah_keluar" class="form-input text-right">
                                <p class="mt-1 text-right text-xs font-medium text-slate-600 dark:text-slate-300">Sisa: <span x-text="(Number(row.stok_awal) || 0) + (Number(row.jumlah_masuk) || 0) - (Number(row.jumlah_keluar) || 0)"></span></p>
                            </div>
                            <div class="sm:col-span-1">
                                <label class="form-label" :for="'opt-' + i">Optimum</label>
                                <input :id="'opt-' + i" type="number" min="0" :name="'rows[' + i + '][stok_optimum]'" x-model.number="row.stok_optimum" class="form-input text-right">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'minta-' + i">Permintaan</label>
                                <input :id="'minta-' + i" type="number" min="0" :name="'rows[' + i + '][permintaan_selanjutnya]'" x-model.number="row.permintaan_selanjutnya" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="form-label" :for="'ket-' + i">Keterangan</label>
                                <input :id="'ket-' + i" type="text" :name="'rows[' + i + '][keterangan]'" x-model="row.keterangan" class="form-input">
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
                <a href="{{ $isEdit ? route('lplpo.show', $laporan) : route('lplpo.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan{{ $isEdit ? '' : ' & generate' }}</button>
            </section>
        </form>
    </div>
@endsection
