@extends('layouts.app')

@section('title', 'Riwayat Stok')
@section('subtitle', 'Mutasi stok masuk dan keluar')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('riwayat-stok.index') }}',
            initial: @js($initial),
            filterKeys: ['search', 'tipe', 'fasilitas', 'obat', 'dari', 'sampai', 'user'],
        })"
    >
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('riwayat-stok.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <input type="search" name="search" x-model="filters.search" @input.debounce.300ms="applyFilter()" placeholder="Cari keterangan, kode, atau nama obat…" class="form-input">
                </div>

                <select name="tipe" x-model="filters.tipe" @change="applyFilter()" class="form-select">
                    <option value="">Semua tipe</option>
                    @foreach ($tipeList as $tipe)
                        <option value="{{ $tipe }}">{{ str($tipe)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>

                @unless ($lockedFaskes)
                    <select name="fasilitas" x-model="filters.fasilitas" @change="applyFilter()" class="form-select">
                        <option value="">Semua faskes + gudang</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}">{{ $f->nama }}</option>
                        @endforeach
                    </select>
                @endunless

                <select name="obat" x-model="filters.obat" @change="applyFilter()" class="form-select">
                    <option value="">Semua obat</option>
                    @foreach ($obatTerpakai as $o)
                        <option value="{{ $o->id }}">{{ $o->nama_obat }}</option>
                    @endforeach
                </select>

                <div class="grid grid-cols-2 gap-3 lg:col-span-2">
                    <input type="date" name="dari" x-model="filters.dari" @change="applyFilter()" class="form-input">
                    <input type="date" name="sampai" x-model="filters.sampai" @change="applyFilter()" class="form-input">
                </div>

                <div>
                    <input type="search" name="user" x-model="filters.user" @input.debounce.300ms="applyFilter()" placeholder="Petugas…" class="form-input">
                </div>

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
                            <th class="px-5 py-3 font-medium">Tanggal</th>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            @unless ($lockedFaskes)
                                <th class="px-5 py-3 font-medium">Faskes</th>
                            @endunless
                            <th class="px-5 py-3 font-medium">Tipe</th>
                            <th class="px-5 py-3 text-right font-medium">Perubahan</th>
                            <th class="px-5 py-3 text-right font-medium">Sebelum → Sesudah</th>
                            <th class="px-5 py-3 font-medium">Petugas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40" :title="row.keterangan || ''">
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="fmtDate(row.tanggal)"></td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100" x-text="row.obat ? row.obat.nama_obat : ''"></p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400" x-text="row.obat ? row.obat.kode_obat : ''"></p>
                                </td>
                                @unless ($lockedFaskes)
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.fasilitas ? row.fasilitas.nama : 'Gudang Dinas'"></td>
                                @endunless
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.tipe.includes('masuk') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : (['opname', 'penyesuaian'].includes(row.tipe) ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300')"
                                        x-text="row.tipe.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold"
                                    :class="row.jumlah > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                                    x-text="(row.jumlah > 0 ? '+' : '') + fmtNum(row.jumlah)"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-xs text-slate-500 dark:text-slate-400" x-text="fmtNum(row.stok_sebelum) + ' → ' + fmtNum(row.stok_sesudah)"></td>
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
