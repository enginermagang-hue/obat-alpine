@php
    $isEdit = $permintaan !== null;
    $rows = old('rows', $oldRows);
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah Permintaan '.$permintaan->nomor_permintaan : 'Permintaan Baru')
@section('subtitle', 'Permintaan obat ke tingkat di atasnya')

@section('content')
    <div
        class="space-y-6"
        x-data="transactionForm({
            obatUrl: '{{ route('api.obat.search') }}',
            initialRows: @js($rows),
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('permintaan.show', $permintaan) : route('permintaan.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
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
            action="{{ $isEdit ? route('permintaan.update', $permintaan) : route('permintaan.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header permintaan</h2>
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="form-label">Pengirim</label>
                        <input type="text" value="{{ $pengirimNama }}" disabled class="form-input">
                    </div>

                    <div>
                        <label class="form-label">Tujuan</label>
                        <input type="text" value="{{ $tujuanNama }}" disabled class="form-input">
                    </div>

                    <div>
                        <label for="tanggal_permintaan" class="form-label">Tanggal *</label>
                        <input id="tanggal_permintaan" name="tanggal_permintaan" type="date" value="{{ old('tanggal_permintaan', $permintaan?->tanggal_permintaan?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="form-input">
                        @error('tanggal_permintaan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="surat_permintaan" class="form-label">No. surat</label>
                        <input id="surat_permintaan" name="surat_permintaan" type="text" value="{{ old('surat_permintaan', $permintaan->surat_permintaan ?? '') }}" placeholder="Opsional" class="form-input">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ old('catatan', $permintaan->catatan ?? '') }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item permintaan</h2>
                    <button type="button" class="btn btn-secondary btn-sm" @click="addRow()">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Tambah baris
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-12">
                            <div class="relative sm:col-span-6">
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

                            <div class="sm:col-span-3">
                                <label class="form-label" :for="'jumlah-' + i">Jumlah diminta *</label>
                                <input :id="'jumlah-' + i" type="number" min="1" :name="'rows[' + i + '][jumlah_diminta]'" x-model.number="row.jumlah" required class="form-input text-right">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'catatan-' + i">Catatan</label>
                                <input :id="'catatan-' + i" type="text" :name="'rows[' + i + '][catatan]'" x-model="row.keterangan" class="form-input">
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
                <a href="{{ $isEdit ? route('permintaan.show', $permintaan) : route('permintaan.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan draft</button>
            </section>
        </form>
    </div>
@endsection
