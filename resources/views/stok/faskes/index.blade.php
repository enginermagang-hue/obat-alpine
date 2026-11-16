@extends('layouts.app')

@section('title', 'Stok Faskes')
@section('subtitle', 'Stok puskesmas dan pustu per obat')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('stok-faskes.index') }}',
            initial: @js($initial),
            filterKeys: ['search', 'fasilitas', 'kategori', 'status_stok'],
        })"
    >
        <section class="grid gap-4 sm:grid-cols-3">
            @php
                $tones = [
                    'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
                    'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
                    'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
                ];
                $cards = [
                    ['label' => 'Total record', 'value' => $kpi['total'], 'icon' => 'building-storefront', 'tone' => 'brand'],
                    ['label' => 'Stok habis', 'value' => $kpi['habis'], 'icon' => 'exclamation-circle', 'tone' => 'red'],
                    ['label' => 'Stok menipis', 'value' => $kpi['menipis'], 'icon' => 'exclamation-triangle', 'tone' => 'amber'],
                ];
            @endphp

            @foreach ($cards as $card)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                        <span class="grid h-9 w-9 place-items-center rounded-xl {{ $tones[$card['tone']] }}">
                            <x-dynamic-component :component="'heroicon-o-' . $card['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ number_format($card['value'], 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        </section>

        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('stok-faskes.recalculate') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary" title="Hitung ulang stok minimum dari rata-rata pemakaian 6 bulan">
                        <x-heroicon-o-calculator class="h-4 w-4" />
                        Hitung stok minimum
                    </button>
                </form>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('stok-faskes.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <input type="search" name="search" x-model="filters.search" @input.debounce.300ms="applyFilter()" placeholder="Cari kode atau nama obat…" class="form-input">
                </div>

                @unless ($lockedFaskes)
                    <select name="fasilitas" x-model="filters.fasilitas" @change="applyFilter()" class="form-select">
                        <option value="">Semua faskes</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}">{{ $f->nama }}</option>
                        @endforeach
                    </select>
                @endunless

                <select name="kategori" x-model="filters.kategori" @change="applyFilter()" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($kategoriList as $kategori)
                        <option value="{{ $kategori }}">{{ $kategori }}</option>
                    @endforeach
                </select>

                <select name="status_stok" x-model="filters.status_stok" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    <option value="tersedia">Tersedia</option>
                    <option value="menipis">Menipis</option>
                    <option value="habis">Habis</option>
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
                            @unless ($lockedFaskes)
                                <th class="px-5 py-3 font-medium">Faskes</th>
                            @endunless
                            <th class="px-5 py-3 font-medium">Kode</th>
                            <th class="px-5 py-3 font-medium">Nama</th>
                            <th class="px-5 py-3 text-right font-medium">
                                <button type="button" @click="setSort('jumlah')" class="uppercase tracking-wide hover:underline">
                                    Jumlah <span x-show="sort === 'jumlah'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-right font-medium">Minimum</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.fasilitas_id + '-' + row.obat_id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                @unless ($lockedFaskes)
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.fasilitas ? row.fasilitas.nama : ''"></td>
                                @endunless
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300" x-text="row.obat ? row.obat.kode_obat : ''"></td>
                                <td class="px-5 py-3">
                                    <a :href="'/stok-faskes/' + row.fasilitas_id + '/' + row.obat_id" class="font-medium text-brand-700 hover:underline dark:text-brand-300" x-text="row.obat ? row.obat.nama_obat : ''"></a>
                                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="row.obat ? (row.obat.kategori + ' · ' + row.obat.satuan) : ''"></p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100" x-text="fmtNum(row.jumlah)"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300" x-text="fmtNum(row.stok_minimum)"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.jumlah <= 0 ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : ((row.stok_minimum > 0 && row.jumlah <= row.stok_minimum) ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300')"
                                        x-text="row.jumlah <= 0 ? 'Habis' : ((row.stok_minimum > 0 && row.jumlah <= row.stok_minimum) ? 'Menipis' : 'Tersedia')"></span>
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
@endsection
