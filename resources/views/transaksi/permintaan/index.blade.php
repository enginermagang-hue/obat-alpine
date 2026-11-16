@extends('layouts.app')

@section('title', 'Permintaan')
@section('subtitle', 'Permintaan obat puskesmas & pustu')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('permintaan.index') }}',
            initial: @js($initial),
            filterKeys: ['search', 'tipe', 'status', 'fasilitas', 'dari', 'sampai'],
        })"
    >
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\PermintaanObat::class)
                    <a href="{{ route('permintaan.create') }}" class="btn btn-primary">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Permintaan Baru
                    </a>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('permintaan.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <input type="search" name="search" x-model.debounce.300ms="filters.search" placeholder="Cari nomor…" class="form-input">
                </div>

                <select name="tipe" x-model="filters.tipe" @change="applyFilter()" class="form-select">
                    <option value="">Semua tipe</option>
                    <option value="puskesmas_ke_dinas">Puskesmas → Dinas</option>
                    <option value="pustu_ke_puskesmas">Pustu → Puskesmas</option>
                </select>

                <select name="status" x-model="filters.status" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    <option value="draft">Draft</option>
                    <option value="menunggu_persetujuan">Menunggu persetujuan</option>
                    <option value="disetujui">Disetujui</option>
                    <option value="ditolak">Ditolak</option>
                    <option value="sedang_didistribusi">Sedang didistribusi</option>
                    <option value="diterima">Diterima</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>

                <select name="fasilitas" x-model="filters.fasilitas" @change="applyFilter()" class="form-select">
                    <option value="">Semua faskes</option>
                    @foreach ($faskesList as $f)
                        <option value="{{ $f->id }}">{{ $f->nama }}</option>
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
                            <th class="px-5 py-3 font-medium">Pengirim → Tujuan</th>
                            <th class="px-5 py-3 font-medium">Tanggal</th>
                            <th class="px-5 py-3 text-right font-medium">Item</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-5 py-3">
                                    <a :href="'/permintaan/' + row.id" class="font-mono text-xs font-medium text-brand-700 hover:underline dark:text-brand-300" x-text="row.nomor_permintaan"></a>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                    <span x-text="row.fasilitas_pengirim ? row.fasilitas_pengirim.nama : ''"></span>
                                    <span class="text-slate-400"> → </span>
                                    <span x-text="row.fasilitas_tujuan ? row.fasilitas_tujuan.nama : 'Dinas'"></span>
                                    <p class="text-xs text-slate-400" x-text="row.tipe_permintaan === 'pustu_ke_puskesmas' ? 'Pustu → Puskesmas' : 'Puskesmas → Dinas'"></p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="fmtDate(row.tanggal_permintaan)"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300" x-text="fmtNum(row.details_count ?? (row.details ? row.details.length : 0))"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.status === 'disetujui' || row.status === 'diterima' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : (row.status === 'ditolak' || row.status === 'dibatalkan' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : (['menunggu_persetujuan', 'sedang_didistribusi'].includes(row.status) ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'))"
                                        x-text="row.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada data yang cocok</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ubah kata kunci atau atur ulang filter.</p>
                            </td>
                        </tr>
                        <tr x-show="loading">
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">Memuat…</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <x-ui.table-pagination />
        </section>
    </div>
@endsection
