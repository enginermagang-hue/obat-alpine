@extends('layouts.app')

@section('title', 'Penerimaan')
@section('subtitle', 'Penerimaan stok dari supplier & hibah')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('penerimaan.index') }}',
            initial: @js($initial),
            filterKeys: ['search', 'tipe', 'status', 'fasilitas', 'supplier', 'dari', 'sampai'],
        })"
    >
        <section class="grid gap-4 sm:grid-cols-3">
            @php
                $tones = [
                    'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
                    'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
                    'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
                ];
                $cards = [
                    ['label' => 'Draft menunggu', 'value' => number_format($kpi['draft'], 0, ',', '.'), 'icon' => 'document-text', 'tone' => 'amber'],
                    ['label' => 'Bulan ini', 'value' => number_format($kpi['bulan_ini'], 0, ',', '.'), 'icon' => 'arrow-down-tray', 'tone' => 'brand'],
                    ['label' => 'Nilai dikonfirmasi (Rp)', 'value' => number_format($kpi['nilai'], 0, ',', '.'), 'icon' => 'banknotes', 'tone' => 'emerald'],
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
                    <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\PenerimaanStok::class)
                    <a href="{{ route('penerimaan.create') }}" class="btn btn-primary">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Penerimaan Baru
                    </a>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('penerimaan.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <input type="search" name="search" x-model.debounce.300ms="filters.search" placeholder="Cari nomor…" class="form-input">
                </div>

                <select name="tipe" x-model="filters.tipe" @change="applyFilter()" class="form-select">
                    <option value="">Semua tipe</option>
                    <option value="pembelian">Pembelian</option>
                    <option value="hibah">Hibah</option>
                    <option value="distribusi">Distribusi</option>
                </select>

                <select name="status" x-model="filters.status" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    <option value="draft">Draft</option>
                    <option value="dikonfirmasi">Dikonfirmasi</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>

                @unless ($lockedFaskes)
                    <select name="fasilitas" x-model="filters.fasilitas" @change="applyFilter()" class="form-select">
                        <option value="">Semua lokasi</option>
                        <option value="gudang">Gudang Dinas</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}">{{ $f->nama }}</option>
                        @endforeach
                    </select>
                @endunless

                <select name="supplier" x-model="filters.supplier" @change="applyFilter()" class="form-select">
                    <option value="">Semua supplier</option>
                    @foreach ($supplierList as $s)
                        <option value="{{ $s->id }}">{{ $s->nama }}</option>
                    @endforeach
                </select>

                <input type="date" name="dari" x-model="filters.dari" @change="applyFilter()" class="form-input">
                <input type="date" name="sampai" x-model="filters.sampai" @change="applyFilter()" class="form-input">

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
                            <th class="px-5 py-3 font-medium">Nomor</th>
                            <th class="px-5 py-3 font-medium">Tipe</th>
                            @unless ($lockedFaskes)
                                <th class="px-5 py-3 font-medium">Lokasi</th>
                            @endunless
                            <th class="px-5 py-3 font-medium">Tanggal</th>
                            <th class="px-5 py-3 text-right font-medium">Nilai</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Petugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-3">
                                    <a :href="'/penerimaan/' + row.id" class="font-mono text-xs font-medium text-brand-700 hover:underline dark:text-brand-300" x-text="row.nomor_penerimaan"></a>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300" x-text="cap(row.tipe)"></span>
                                </td>
                                @unless ($lockedFaskes)
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.fasilitas ? row.fasilitas.nama : 'Gudang Dinas'"></td>
                                @endunless
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="fmtDate(row.tanggal_penerimaan)"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100" x-text="fmtRupiah(row.total_biaya)"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.status === 'dikonfirmasi' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : (row.status === 'dibatalkan' ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300')"
                                        x-text="row.status === 'dikonfirmasi' ? 'Dikonfirmasi' : (row.status === 'dibatalkan' ? 'Dibatalkan' : 'Draft')"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.user ? row.user.name : '—'"></td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="7" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada data yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ubah kata kunci atau atur ulang filter.</p>
                            </td>
                        </tr>
                        <tr x-show="loading">
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">Memuat…</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <x-ui.table-pagination />
        </section>
    </div>
@endsection
