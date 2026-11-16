@extends('layouts.app')

@section('title', 'Batch')
@section('subtitle', 'Batch stok per obat dan fasilitas')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('batch.index') }}',
            initial: @js($initial),
            filterKeys: ['search', 'fasilitas', 'status', 'expiry'],
        })"
    >
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $tones = [
                    'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
                    'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
                    'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
                    'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
                ];
                $cards = [
                    ['label' => 'Batch aktif', 'value' => number_format($kpi['total'], 0, ',', '.'), 'icon' => 'queue-list', 'tone' => 'brand'],
                    ['label' => 'Nilai stok (Rp)', 'value' => number_format($kpi['nilai'], 0, ',', '.'), 'icon' => 'banknotes', 'tone' => 'emerald'],
                    ['label' => 'Sudah kedaluwarsa', 'value' => number_format($kpi['lewat'], 0, ',', '.'), 'icon' => 'exclamation-circle', 'tone' => 'red'],
                    ['label' => 'Kedaluwarsa < 90 hari', 'value' => number_format($kpi['d90'], 0, ',', '.'), 'icon' => 'clock', 'tone' => 'amber'],
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

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('batch.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <input type="search" name="search" x-model="filters.search" @input.debounce.300ms="applyFilter()" placeholder="Cari batch, kode, atau nama obat…" class="form-input">
                </div>

                @unless ($lockedFaskes)
                    <select name="fasilitas" x-model="filters.fasilitas" @change="applyFilter()" class="form-select">
                        <option value="">Semua faskes + gudang</option>
                        <option value="gudang">Gudang Dinas</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}">{{ $f->nama }}</option>
                        @endforeach
                    </select>
                @endunless

                <select name="status" x-model="filters.status" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    @foreach ($statusList as $s)
                        <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                    @endforeach
                </select>

                <select name="expiry" x-model="filters.expiry" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua kedaluwarsa</option>
                    <option value="d90">&lt; 90 hari</option>
                    <option value="d30">&lt; 30 hari</option>
                    <option value="lewat">Sudah lewat</option>
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
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 font-medium">Batch</th>
                            @unless ($lockedFaskes)
                                <th class="px-5 py-3 font-medium">Faskes</th>
                            @endunless
                            <th class="px-5 py-3 text-right font-medium">
                                <button type="button" @click="setSort('jumlah')" class="uppercase tracking-wide hover:underline">
                                    Jumlah <span x-show="sort === 'jumlah'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">
                                <button type="button" @click="setSort('expired')" class="uppercase tracking-wide hover:underline">
                                    Kedaluwarsa <span x-show="sort === 'expired'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                                </button>
                            </th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 text-right font-medium">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100" x-text="row.obat ? row.obat.nama_obat : ''"></p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400" x-text="row.obat ? (row.obat.kode_obat + ' · ' + row.obat.satuan) : ''"></p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300" x-text="row.batch_number"></td>
                                @unless ($lockedFaskes)
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.fasilitas ? row.fasilitas.nama : 'Gudang Dinas'"></td>
                                @endunless
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100" x-text="fmtNum(row.jumlah)"></td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="isPast(row.tanggal_expired) ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : (isNear(row.tanggal_expired) ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300')"
                                        x-text="fmtDate(row.tanggal_expired)"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.status === 'tersedia' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : (row.status === 'karantina' ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300')"
                                        x-text="cap(row.status)"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300" x-text="row.harga_beli ? fmtRupiah(row.jumlah * row.harga_beli) : '—'"></td>
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
