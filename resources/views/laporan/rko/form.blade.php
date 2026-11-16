@php
    $isEdit = $rko !== null;
    $rows = old('rows', $oldRows);
@endphp

@extends('layouts.app')

@section('title', $isEdit ? 'Ubah RKO '.$rko->nomor_rko : 'RKO Baru '.$tahun)
@section('subtitle', 'Rencana kebutuhan obat tahunan')

@section('content')
    <div
        class="space-y-6"
        x-data="transactionForm({
            obatUrl: '{{ route('api.obat.search') }}',
            initialRows: @js($rows),
        })"
    >
        <section>
            <a href="{{ $isEdit ? route('rko.show', $rko) : route('rko.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali
            </a>
        </section>

        @if ($errors->any())
            <section class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                <p class="font-medium">Periksa kembali isian berikut:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form
            method="POST"
            action="{{ $isEdit ? route('rko.update', $rko) : route('rko.store') }}"
            class="space-y-6"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Header RKO — {{ $faskesNama }} ({{ $tahun }})</h2>
                    @if (! $isEdit && $previewCount > 0)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $previewCount }} baris terisi otomatis dari riwayat pemakaian & stok. Sesuaikan bila perlu.</p>
                    @endif
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="tanggal_pembuatan" class="form-label">Tanggal pembuatan *</label>
                        <input id="tanggal_pembuatan" name="tanggal_pembuatan" type="date" value="{{ old('tanggal_pembuatan', $rko?->tanggal_pembuatan?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="form-input">
                        @error('tanggal_pembuatan')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ old('catatan', $rko->catatan ?? '') }}" class="form-input">
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Item RKO</h2>
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
                                <label class="form-label" :for="'pakai-' + i">Pemakaian thn lalu</label>
                                <input :id="'pakai-' + i" type="number" min="0" :name="'rows[' + i + '][pemakaian_tahun_sebelumnya]'" x-model.number="row.pemakaian_tahun_sebelumnya" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'rata-' + i">Rata2/bln</label>
                                <input :id="'rata-' + i" type="number" min="0" :name="'rows[' + i + '][rata_rata_pemakaian_bulanan]'" x-model.number="row.rata_rata_pemakaian_bulanan" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'stok-' + i">Stok akhir</label>
                                <input :id="'stok-' + i" type="number" min="0" :name="'rows[' + i + '][stok_akhir]'" x-model.number="row.stok_akhir" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'usul-' + i">Usulan *</label>
                                <input :id="'usul-' + i" type="number" min="0" :name="'rows[' + i + '][usulan]'" x-model.number="row.usulan" required class="form-input text-right">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'harga-' + i">Harga perkiraaan</label>
                                <input :id="'harga-' + i" type="number" min="0" step="0.01" :name="'rows[' + i + '][harga_perkiraan]'" x-model="row.harga_perkiraan" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" :for="'buf-' + i">Buffer %</label>
                                <input :id="'buf-' + i" type="number" min="0" max="100" step="0.01" :name="'rows[' + i + '][buffer_stock_persen]'" x-model="row.buffer_stock_persen" class="form-input text-right">
                            </div>
                            <div class="sm:col-span-1">
                                <label class="form-label">Total</label>
                                <p class="rounded-xl bg-slate-50 px-3 py-2 text-right text-sm font-semibold dark:bg-slate-800" x-text="fmtNum(Math.round((Number(row.usulan) || 0) * (1 + (Number(row.buffer_stock_persen) || 0) / 100)))"></p>
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
                <a href="{{ $isEdit ? route('rko.show', $rko) : route('rko.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan draft</button>
            </section>
        </form>
    </div>
@endsection
