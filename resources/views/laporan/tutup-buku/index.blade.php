@extends('layouts.app')

@section('title', 'Tutup Buku')
@section('subtitle', 'Cut-off periode & kunci transaksi')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('tutup-buku.index') }}',
            initial: @js($initial),
            filterKeys: ['tipe', 'status', 'tahun'],
            extra: { bukaId: null, alasan: '' },
        })"
    >
        @can('create', App\Models\TutupBuku::class)
            <section class="grid gap-6 lg:grid-cols-2">
                <form method="POST" action="{{ route('tutup-buku.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    <input type="hidden" name="tipe" value="inisial">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Cut-Off Inisial</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Arsip stok sebelum tanggal go-live menjadi neraca awal.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="cutoff" class="form-label">Tanggal go-live *</label>
                            <input id="cutoff" type="date" name="cutoff" required class="form-input">
                        </div>
                        <div>
                            <label for="catatan-inisial" class="form-label">Catatan</label>
                            <input id="catatan-inisial" type="text" name="catatan" class="form-input">
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">Jalankan cut-off</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('tutup-buku.store') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    <input type="hidden" name="tipe" value="tahunan">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Tutup Tahun</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kunci semua transaksi tahun berjalan.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="tahun" class="form-label">Tahun *</label>
                            <input id="tahun" type="number" name="tahun" min="2000" max="2100" value="{{ now()->year }}" required class="form-input">
                        </div>
                        <div>
                            <label for="deadline" class="form-label">Deadline (opsional)</label>
                            <input id="deadline" type="date" name="deadline" class="form-input">
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">Tutup tahun</button>
                    </div>
                </form>
            </section>
        @endcan

        @if (session('error'))
            <section class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                {{ session('error') }}
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('tutup-buku.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <select name="tipe" x-model="filters.tipe" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua tipe</option>
                    <option value="inisial">Inisial</option>
                    <option value="tahunan">Tahunan</option>
                </select>

                <select name="status" x-model="filters.status" @change="applyFilter()" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    <option value="tutup">Tutup</option>
                    <option value="dibuka_kembali">Dibuka kembali</option>
                    <option value="draft">Draft</option>
                </select>

                <input type="number" name="tahun" x-model="filters.tahun" @change="applyFilter()" min="2000" max="2100" placeholder="Tahun" class="form-input">

                <div class="flex gap-2 sm:col-span-2 lg:col-span-2">
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
                            <th class="px-5 py-3 font-medium">Tipe</th>
                            <th class="px-5 py-3 font-medium">Tahun</th>
                            <th class="px-5 py-3 font-medium">Cut-off</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Ditutup oleh</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.tipe === 'inisial' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'"
                                        x-text="row.tipe === 'inisial' ? 'Inisial' : 'Tahunan'"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-800 dark:text-slate-100" x-text="row.tahun"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="fmtDate(row.cutoff_at)"></td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="row.status === 'tutup' ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' : (row.status === 'dibuka_kembali' ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300')"
                                        x-text="row.status.replace(/_/g, ' ')"></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.ditutup_oleh ? row.ditutup_oleh.name : '—'"></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <template x-if="row.status === 'tutup'">
                                            <button type="button" class="btn-icon" title="Buka kembali" @click="bukaId = row.id; alasan = ''">
                                                <x-heroicon-o-lock-open class="h-4 w-4" />
                                            </button>
                                        </template>
                                        <template x-if="row.status === 'dibuka_kembali'">
                                            <form method="POST" :action="'/tutup-buku/' + row.id + '/tutup-kembali'" class="flex items-center gap-1" @submit="return confirm('Tutup kembali periode ini?')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn-icon" title="Tutup kembali">
                                                    <x-heroicon-o-lock-closed class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Belum ada tutup buku</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Jalankan cut-off inisial atau tutup tahun di atas.</p>
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

        <div x-show="bukaId" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="bukaId = null">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="bukaId = null"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <form method="POST" :action="bukaId ? '/tutup-buku/' + bukaId + '/buka-kembali' : '#'" class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Buka kembali tutup buku</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Transaksi pada periode terkait bisa diinput lagi.</p>
                    <label for="alasan-buka" class="form-label mt-4">Alasan *</label>
                    <textarea id="alasan-buka" name="alasan" rows="3" required x-model="alasan" class="form-textarea" placeholder="Contoh: koreksi data bulan berjalan"></textarea>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" class="btn btn-ghost" @click="bukaId = null">Batal</button>
                        <button type="submit" class="btn btn-primary" :disabled="!alasan.trim()">Buka kembali</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
