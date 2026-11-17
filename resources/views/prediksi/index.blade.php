@extends('layouts.app')

@section('title', 'Prediksi AI')
@section('subtitle', 'Rekomendasi pengadaan berbasis prediksi AI')

@section('content')
    <div
        class="space-y-6"
        x-data="dataTable({
            url: '{{ route('prediksi.index') }}',
            initial: @js($initial),
            filterKeys: ['fasilitas_id', 'kategori', 'cari', 'bulan', 'tahun', 'horizon'],
            ...prediksiDetail(),
        })"
    >
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ session('error') }}</div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $kpis = [
                    ['label' => 'Obat diprediksi / aktif', 'value' => number_format($kpi['obat_diprediksi'] ?? 0, 0, ',', '.').' / '.number_format($kpi['total_obat_aktif'] ?? 0, 0, ',', '.')],
                    ['label' => 'Obat defisit', 'value' => number_format($kpi['obat_defisit'] ?? 0, 0, ',', '.')],
                    ['label' => 'Estimasi anggaran', 'value' => 'Rp '.number_format($kpi['estimasi_anggaran'] ?? 0, 0, ',', '.')],
                    ['label' => 'Akurasi model', 'value' => number_format(($akurasiModel ?? 0) * 100, 1).'%'],
                ];
            @endphp
            @foreach ($kpis as $c)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $c['label'] }}</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">{{ $c['value'] }}</p>
                </div>
            @endforeach
        </section>

        @if (collect($lonjakan)->where('rekom', '>', 0)->isNotEmpty())
            @php $top = collect($lonjakan)->where('rekom', '>', 0)->first(); @endphp
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                <p class="text-xs font-semibold uppercase tracking-wide">Insight AI hari ini</p>
                <p class="mt-1">Model mendeteksi lonjakan kebutuhan <strong>{{ $top['nama_obat'] }}</strong>
                    (rekomendasi {{ number_format($top['rekom'], 0, ',', '.') }} {{ $top['satuan'] }}).
                    <strong>{{ $kpi['obat_defisit'] ?? 0 }}</strong> obat berisiko kekurangan stok dalam 21 hari.
                </p>
                <p class="mt-1 text-xs opacity-70">Confidence AI {{ number_format(($akurasiModel ?? 0) * 100, 1) }}% · Diperbarui hari ini</p>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Realisasi vs Prediksi</h2>
            <div class="mt-3 h-64"><canvas x-chart="@js($chart)"></canvas></div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Top {{ count($lonjakan) }} kebutuhan tertinggi</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @foreach ($lonjakan as $r)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800 dark:text-slate-100">{{ $r['nama_obat'] }}</p>
                            <p class="text-xs text-slate-500">Rekom {{ number_format($r['rekom'], 0, ',', '.') }} · Prediksi {{ number_format($r['prediksi_horizon'], 0, ',', '.') }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" @click="openDetail({{ $r['obat_id'] }})">Detail</button>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('prediksi.index') }}" @submit.prevent="applyFilter()" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-7">
                <select name="fasilitas_id" x-model="filters.fasilitas_id" @change="applyFilter()" class="form-select" @disabled($isFaskes)>
                    <option value="">Semua faskes</option>
                    @foreach ($faskesList as $f)
                        <option value="{{ $f->id }}">{{ $f->nama }}</option>
                    @endforeach
                </select>
                <select name="kategori" x-model="filters.kategori" @change="applyFilter()" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($kategoriList as $k)
                        <option value="{{ $k }}">{{ $k }}</option>
                    @endforeach
                </select>
                <input type="text" name="cari" x-model.debounce.300ms="filters.cari" placeholder="Cari obat…" class="form-input">
                <input type="number" name="bulan" x-model="filters.bulan" @change="applyFilter()" min="1" max="12" class="form-input" placeholder="Bulan">
                <input type="number" name="tahun" x-model="filters.tahun" @change="applyFilter()" min="2000" max="2100" class="form-input" placeholder="Tahun">
                <select name="horizon" x-model="filters.horizon" @change="applyFilter()" class="form-select">
                    @foreach ([1, 3, 6, 12] as $h)
                        <option value="{{ $h }}">{{ $h }} bulan</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a :href="exportUrl('{{ route('prediksi.export') }}')" class="btn btn-ghost">Excel</a>
                </div>
            </form>

            <div x-show="error" x-cloak class="mx-5 mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"> <span x-text="error"></span></div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800" :class="{ 'opacity-50': loading }">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium"><button type="button" @click="setSort('nama_obat')">Obat</button></th>
                            <th class="px-5 py-3 text-right font-medium"><button type="button" @click="setSort('stok')">Stok</button></th>
                            <th class="px-5 py-3 text-right font-medium"><button type="button" @click="setSort('prediksi_horizon')">Prediksi</button></th>
                            <th class="px-5 py-3 text-right font-medium"><button type="button" @click="setSort('rekom')">Rekom</button></th>
                            <th class="px-5 py-3 text-center font-medium">Status</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.obat_id">
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3"><span class="font-medium text-slate-800 dark:text-slate-100" x-text="row.nama_obat"></span><span class="block text-xs text-slate-500" x-text="row.kategori ?? ''"></span></td>
                                <td class="px-5 py-3 text-right" x-text="fmtNum(row.stok)"></td>
                                <td class="px-5 py-3 text-right" x-text="fmtNum(row.prediksi_horizon)"></td>
                                <td class="px-5 py-3 text-right font-semibold" x-text="fmtNum(row.rekom)"></td>
                                <td class="px-5 py-3 text-center"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium dark:bg-slate-800" x-text="row.status"></span></td>
                                <td class="px-5 py-3 text-right"><button type="button" class="btn btn-ghost btn-sm" @click="openDetail(row.obat_id)">Detail</button></td>
                            </tr>
                        </template>
                        <tr x-show="!loading && rows.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center">
                                @if (!$hasPrediksi)
                                    <p class="text-sm font-medium">Belum ada data prediksi. Jalankan <code>php artisan ai:train-models --force</code>.</p>
                                @else
                                    <p class="text-sm font-medium">Tidak ada data untuk filter ini.</p>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <x-ui.table-pagination />
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Model AI (10 terbaru)</h2>
                @can('view_model_prediksi')
                    @role('super_admin')
                        <form method="POST" action="{{ route('prediksi.model.train-semua') }}">@csrf<button class="btn btn-dark btn-sm">Latih semua</button></form>
                    @endrole
                @endcan
            </div>
            <p class="mt-1 text-xs text-slate-500">Aktif: {{ $modelStats['aktif'] }} · Kadaluarsa: {{ $modelStats['kadaluarsa'] }} · Gagal: {{ $modelStats['gagal'] }} · Data kurang: {{ $modelStats['data_belum_cukup'] }}</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2 pr-4">Faskes</th><th class="py-2 pr-4">Obat</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4 text-right">Akurasi</th><th class="py-2 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($models as $m)
                            <tr>
                                <td class="py-2 pr-4">{{ $m->fasilitas?->nama ?? '-' }}</td>
                                <td class="py-2 pr-4">{{ $m->obat?->nama_obat ?? '-' }}</td>
                                <td class="py-2 pr-4"><x-ui.status-badge :status="$m->getStatusColor($m->status)" :label="$m->status" /></td>
                                <td class="py-2 pr-4 text-right">{{ $m->akurasi_r2 !== null ? number_format((float) $m->akurasi_r2 * 100, 1).'%' : '—' }}</td>
                                <td class="py-2 text-right">
                                    @can('view_model_prediksi')
                                        @role('super_admin|admin_gudang')
                                            <form method="POST" action="{{ route('prediksi.model.train', $m) }}">@csrf<button class="btn btn-ghost btn-sm">Latih ulang</button></form>
                                        @endrole
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-slate-500">Belum ada model AI.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-500">Training berjalan via queue. Di dev jalankan <code>php artisan queue:work</code>.</p>
        </section>

        @if ($canBuatPermintaan)
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Buat permintaan dari rekomendasi</h2>
                <p class="mt-1 text-xs text-slate-500">Membuat draft permintaan dengan jumlah = rekomendasi untuk filter aktif (hanya defisit).</p>
                <form method="POST" action="{{ route('prediksi.buat-permintaan') }}" class="mt-3 flex flex-wrap gap-2">
                    @csrf
                    <input type="hidden" name="bulan" :value="filters.bulan">
                    <input type="hidden" name="tahun" :value="filters.tahun">
                    <input type="hidden" name="horizon" :value="filters.horizon">
                    <button class="btn btn-dark">Buat Permintaan</button>
                </form>
            </section>
        @endif

        <div x-show="detailOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/50" @click="detailOpen = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-3xl rounded-2xl bg-white p-5 shadow-xl dark:bg-slate-900" @click.stop>
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold" x-text="detail?.ringkasan?.nama_obat ?? 'Detail'"></h2>
                        <button type="button" class="btn btn-ghost btn-sm" @click="detailOpen = false">Tutup</button>
                    </div>
                    <div x-show="detailLoading" class="py-8 text-center text-sm text-slate-500">Memuat…</div>
                    <template x-if="detail">
                        <div class="mt-4 space-y-4 text-sm">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div><p class="text-xs text-slate-500">Stok</p><p class="font-semibold" x-text="fmtNum(detail.ringkasan.stok)"></p></div>
                                <div><p class="text-xs text-slate-500">Prediksi horizon</p><p class="font-semibold" x-text="fmtNum(detail.ringkasan.prediksi_horizon)"></p></div>
                                <div><p class="text-xs text-slate-500">Rekomendasi</p><p class="font-semibold" x-text="fmtNum(detail.ringkasan.rekom)"></p></div>
                                <div><p class="text-xs text-slate-500">Stok gudang</p><p class="font-semibold" x-text="fmtNum(detail.stok_gudang)"></p></div>
                            </div>
                            <div>
                                <h3 class="text-xs font-semibold uppercase text-slate-500">Prediksi per bulan</h3>
                                <ul class="mt-1 space-y-1">
                                    <template x-for="b in detail.bulanan" :key="b.label">
                                        <li class="flex justify-between"><span x-text="b.label"></span><span x-text="fmtNum(b.jumlah)"></span></li>
                                    </template>
                                </ul>
                            </div>
                            <div>
                                <h3 class="text-xs font-semibold uppercase text-slate-500">Faktor AI</h3>
                                <ul class="mt-1 list-disc pl-5">
                                    <template x-for="f in detail.factors" :key="f.judul">
                                        <li><span class="font-medium" x-text="f.judul"></span> — <span x-text="f.sub"></span></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endsection
