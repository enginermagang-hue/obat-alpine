@php
    $isEdit = $distribusi !== null;
    $headerFaskes = $defaultPengirim;
    $rows = old('rows', $oldRows);
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah Distribusi '.$distribusi->nomor_surat_jalan : 'Distribusi Baru')
@section('subtitle', 'Alokasi batch otomatis (FEFO/FIFO/LIFO)')

@section('content')
    <div
        class="space-y-6"
        x-data="transactionForm({
            obatUrl: '{{ route('api.obat.search') }}',
            alokasiUrl: '{{ route('api.distribusi.alokasi') }}',
            fasilitasId: @js($headerFaskes),
            initialRows: @js($rows),
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('distribusi.show', $distribusi) : route('distribusi.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
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
            action="{{ $isEdit ? route('distribusi.update', $distribusi) : route('distribusi.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif
            @if ($permintaan)
                <input type="hidden" name="permintaan_id" value="{{ $permintaan->id }}">
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header distribusi</h2>
                    @if ($permintaan)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Menindaklanjuti permintaan {{ $permintaan->nomor_permintaan }}</p>
                    @endif
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="tipe_distribusi" class="form-label">Tipe *</label>
                        <select id="tipe_distribusi" name="tipe_distribusi" class="form-select">
                            <option value="dinas_ke_puskesmas" @selected($defaultTipe === 'dinas_ke_puskesmas')>Dinas → Puskesmas</option>
                            <option value="puskesmas_ke_pustu" @selected($defaultTipe === 'puskesmas_ke_pustu')>Puskesmas → Pustu</option>
                        </select>
                        @error('tipe_distribusi')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="fasilitas_pengirim_id" class="form-label">Pengirim</label>
                        @if ($lockedPengirim)
                            <input type="text" value="{{ $faskesList->firstWhere('id', $defaultPengirim)?->nama ?? 'Faskes saya' }}" disabled class="form-input">
                            <input type="hidden" name="fasilitas_pengirim_id" value="{{ $defaultPengirim }}">
                        @else
                            <select id="fasilitas_pengirim_id" name="fasilitas_pengirim_id" x-model="fasilitasId" @change="onFaskesChange(); loadAlokasi()" class="form-select">
                                <option value="">Gudang Dinas</option>
                                @foreach ($faskesList as $f)
                                    <option value="{{ $f->id }}" @selected((string) old('fasilitas_pengirim_id', $defaultPengirim ?? '') === (string) $f->id)>{{ $f->nama }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('fasilitas_pengirim_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="fasilitas_penerima_id" class="form-label">Penerima *</label>
                        <select id="fasilitas_penerima_id" name="fasilitas_penerima_id" class="form-select">
                            <option value="">— Pilih —</option>
                            @foreach ($faskesList as $f)
                                <option value="{{ $f->id }}" @selected((string) old('fasilitas_penerima_id', $defaultPenerima) === (string) $f->id)>{{ $f->nama }} ({{ ucfirst($f->tipe) }})</option>
                            @endforeach
                        </select>
                        @error('fasilitas_penerima_id')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="tanggal_kirim" class="form-label">Tanggal kirim</label>
                        <input id="tanggal_kirim" name="tanggal_kirim" type="date" value="{{ old('tanggal_kirim', $distribusi?->tanggal_kirim?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="form-input">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ old('catatan', $distribusi->catatan ?? '') }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item distribusi</h2>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" @click="loadAlokasi()" :disabled="alokasiLoading">
                            <x-heroicon-o-sparkles class="h-4 w-4" />
                            <span x-text="alokasiLoading ? 'Menghitung…' : 'Preview alokasi FEFO'"></span>
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" @click="addRow()">
                            <x-heroicon-o-plus class="h-4 w-4" />
                            Tambah baris
                        </button>
                    </div>
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
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="pickObat(i, item); loadAlokasi()">
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
                                <input :id="'jumlah-' + i" type="number" min="1" :name="'rows[' + i + '][jumlah]'" x-model.number="row.jumlah" @change="loadAlokasi()" required class="form-input text-right">
                            </div>

                            <div class="sm:col-span-3">
                                <label class="form-label">Alokasi batch</label>
                                <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs dark:bg-slate-800" x-show="row.alokasi && row.alokasi.length">
                                    <template x-for="a in row.alokasi" :key="a.batch_id">
                                        <p class="text-slate-600 dark:text-slate-300">
                                            <span class="font-mono" x-text="a.batch_number"></span>
                                            · <span x-text="a.jumlah"></span>
                                            · <span x-text="a.tanggal_expired"></span>
                                        </p>
                                    </template>
                                </div>
                                <p class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-400 dark:bg-slate-800" x-show="!row.alokasi || !row.alokasi.length">Tekan “Preview alokasi FEFO”.</p>
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
                <a href="{{ $isEdit ? route('distribusi.show', $distribusi) : route('distribusi.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan draft</button>
            </section>
        </form>
    </div>
@endsection
