@extends('layouts.app')

@section('title', 'Stok Gudang')
@section('subtitle', 'Agregat stok Gudang Dinas per obat')

@section('content')
    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $cards = [
                    ['label' => 'Total obat', 'value' => $kpi['total'], 'icon' => 'archive-box', 'tone' => 'brand'],
                    ['label' => 'Stok habis', 'value' => $kpi['habis'], 'icon' => 'exclamation-circle', 'tone' => 'red'],
                    ['label' => 'Stok menipis', 'value' => $kpi['menipis'], 'icon' => 'exclamation-triangle', 'tone' => 'amber'],
                    ['label' => 'Nilai stok (Rp)', 'value' => number_format($kpi['nilai'], 0, ',', '.'), 'icon' => 'banknotes', 'tone' => 'emerald'],
                ];
                $tones = [
                    'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
                    'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300',
                    'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
                    'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
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
                        {{ $card['value'] }}
                    </p>
                </div>
            @endforeach
        </section>

        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('stok-gudang.recalculate') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary" title="Hitung ulang stok minimum dari rata-rata distribusi 6 bulan">
                        <x-heroicon-o-calculator class="h-4 w-4" />
                        Hitung stok minimum
                    </button>
                </form>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('stok-gudang.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari kode atau nama obat…" class="form-input">
                </div>

                <select name="kategori" class="form-select">
                    <option value="">Semua kategori</option>
                    @foreach ($kategoriList as $kategori)
                        <option value="{{ $kategori }}" @selected(($filters['kategori'] ?? '') === $kategori)>{{ $kategori }}</option>
                    @endforeach
                </select>

                <select name="status_stok" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    <option value="tersedia" @selected(($filters['status_stok'] ?? '') === 'tersedia')>Tersedia</option>
                    <option value="menipis" @selected(($filters['status_stok'] ?? '') === 'menipis')>Menipis</option>
                    <option value="habis" @selected(($filters['status_stok'] ?? '') === 'habis')>Habis</option>
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a href="{{ route('stok-gudang.index') }}" class="btn btn-ghost">Atur ulang</a>
                </div>
            </form>

            @if ($stok->isEmpty())
                <x-ui.empty-state title="Belum ada data stok gudang" hint="Data muncul setelah ada penerimaan stok." icon="archive-box" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Kode</th>
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Kategori</th>
                                <th class="px-5 py-3 text-right font-medium">
                                    <a href="{{ route('stok-gudang.index', array_merge(request()->query(), ['sort' => (request('sort') === 'jumlah_desc' ? 'jumlah_asc' : 'jumlah_desc')])) }}" class="hover:underline">
                                        Jumlah {{ request('sort') === 'jumlah_desc' ? '↓' : (request('sort') === 'jumlah_asc' ? '↑' : '') }}
                                    </a>
                                </th>
                                <th class="px-5 py-3 text-right font-medium">Minimum</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($stok as $row)
                                @php
                                    $status = $row->jumlah <= 0 ? 'habis' : (($row->stok_minimum > 0 && $row->jumlah <= $row->stok_minimum) ? 'menipis' : 'tersedia');
                                    $tone = $status === 'habis' ? 'red' : ($status === 'menipis' ? 'amber' : 'emerald');
                                    $label = $status === 'habis' ? 'Habis' : ($status === 'menipis' ? 'Menipis' : 'Tersedia');
                                @endphp
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row->obat?->kode_obat }}</td>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('stok-gudang.show', $row->obat_id) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">
                                            {{ $row->obat?->nama_obat }}
                                        </a>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $row->obat?->satuan }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->obat?->kategori }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($row->jumlah, 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($row->stok_minimum, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.pagination :paginator="$stok" />
            @endif
        </section>
    </div>
@endsection
