@extends('layouts.app')

@section('title', 'Alokasi Dana')
@section('subtitle', 'Anggaran, alokasi & realisasi per sumber dana')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('alokasi-dana.index') }}',
            initial: @js($initial),
            filterKeys: ['tahun', 'sumber_dana'],
        })"
    >
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Anggaran {{ $tahun }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Rp {{ number_format($stats['anggaran'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Realisasi</p>
                <p class="mt-1 text-lg font-semibold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($stats['realisasi'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Alokasi ke faskes</p>
                <p class="mt-1 text-lg font-semibold text-brand-600 dark:text-brand-300">Rp {{ number_format($stats['alokasi'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Perencanaan</p>
                <p class="mt-1 text-lg font-semibold text-amber-600 dark:text-amber-400">Rp {{ number_format($stats['perencanaan'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Sisa</p>
                <p class="mt-1 text-lg font-semibold {{ $stats['sisa'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">Rp {{ number_format($stats['sisa'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">% Terpakai</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ number_format($stats['persen'], 1) }}%</p>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Anggaran vs Realisasi per Sumber Dana</h2>
                <div class="mt-3 h-64"><canvas x-chart="@js($charts['bar'])"></canvas></div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Komposisi per Tipe Penggunaan</h2>
                <div class="mt-3 h-64"><canvas x-chart="@js($charts['doughnut'])"></canvas></div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Tren Realisasi Bulanan {{ $tahun }}</h2>
                <div class="mt-3 h-64"><canvas x-chart="@js($charts['tren'])"></canvas></div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('alokasi-dana.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                <input type="number" name="tahun" x-model="filters.tahun" @change="applyFilter()" min="2000" max="2100" placeholder="Tahun" class="form-input">

                <select name="sumber_dana" x-model="filters.sumber_dana" @change="applyFilter()" class="form-select">
                    <option value="">Semua sumber dana</option>
                    @foreach ($sumberDanaList as $sd)
                        <option value="{{ $sd->id }}">{{ $sd->kode }} ({{ $sd->tahun }}) — {{ $sd->nama }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
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
                            <th class="px-5 py-3 font-medium">Kode</th>
                            <th class="px-5 py-3 font-medium">Nama</th>
                            <th class="px-5 py-3 text-right font-medium">Anggaran</th>
                            <th class="px-5 py-3 text-right font-medium">Realisasi</th>
                            <th class="px-5 py-3 text-right font-medium">Sisa</th>
                            <th class="px-5 py-3 text-center font-medium">%</th>
                            <th class="px-5 py-3 text-center font-medium">PO</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs font-medium text-slate-800 dark:text-slate-100" x-text="row.kode"></td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.nama"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100" x-text="fmtRupiah(row.total_anggaran)"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-emerald-600 dark:text-emerald-400" x-text="fmtRupiah(row.total_realisasi ?? 0)"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100" x-text="fmtRupiah(row.sisa ?? 0)"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-center text-slate-600 dark:text-slate-300" x-text="(row.persen ?? 0) + '%'"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-center text-slate-600 dark:text-slate-300" x-text="row.jumlah_po ?? 0"></td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="7" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada data yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ubah filter atau atur ulang.</p>
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
