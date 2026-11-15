@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Ringkasan stok dan aktivitas terbaru')

@section('content')
    @php
        $hour = (int) now()->format('H');
        $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
        $roles = $user->getRoleNames()->map(fn ($role) => str($role)->replace('_', ' ')->title())->implode(', ');
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-white/80">{{ $greeting }},</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $user->name }}</h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ $fasilitas?->nama ?? 'Gudang Farmasi Dinas Kesehatan' }}
                        @if ($roles)
                            &middot; {{ $roles }}
                        @endif
                    </p>
                </div>

                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Hari ini</p>
                    <p class="font-medium">{{ now()->translatedFormat('l, d F Y') }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $cards = [
                    ['label' => 'Obat aktif', 'value' => $kpi['obat'], 'icon' => 'beaker', 'tone' => 'brand'],
                    ['label' => 'Fasilitas kesehatan', 'value' => $kpi['fasilitas'], 'icon' => 'building-office-2', 'tone' => 'accent'],
                    ['label' => 'Batch < 90 hari', 'value' => $kpi['expired'], 'icon' => 'exclamation-triangle', 'tone' => 'amber'],
                    ['label' => 'Pemakaian bulan ini', 'value' => $kpi['pemakaian'], 'icon' => 'clipboard-document-list', 'tone' => 'violet'],
                ];
            @endphp

            @foreach ($cards as $card)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                        <span @class([
                            'grid h-9 w-9 place-items-center rounded-xl',
                            'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300' => $card['tone'] === 'brand',
                            'bg-accent-500/10 text-accent-600 dark:text-accent-300' => $card['tone'] === 'accent',
                            'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300' => $card['tone'] === 'amber',
                            'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300' => $card['tone'] === 'violet',
                        ])>
                            <x-dynamic-component :component="'heroicon-o-' . $card['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ number_format($card['value'], 0, ',', '.') }}
                    </p>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="xl:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Batch mendekati kedaluwarsa</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">90 hari ke depan</p>
                        </div>
                        <x-heroicon-o-clock class="h-5 w-5 text-slate-400" />
                    </div>

                    @if ($batchExpired->isEmpty())
                        <div class="px-5 py-12 text-center">
                            <x-heroicon-o-check-badge class="mx-auto h-10 w-10 text-emerald-500/70" />
                            <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-200">Tidak ada batch mendekati kedaluwarsa</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Data batch akan muncul setelah ada penerimaan stok.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                    <tr>
                                        <th class="px-5 py-3 font-medium">Obat</th>
                                        <th class="px-5 py-3 font-medium">Batch</th>
                                        <th class="px-5 py-3 font-medium">Fasilitas</th>
                                        <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                                        <th class="px-5 py-3 font-medium">Kedaluwarsa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach ($batchExpired as $batch)
                                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                            <td class="px-5 py-3">
                                                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $batch->obat?->nama_obat }}</p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $batch->obat?->satuan }}</p>
                                            </td>
                                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $batch->batch_number }}</td>
                                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $batch->fasilitas?->nama ?? 'Gudang Dinas' }}</td>
                                            <td class="px-5 py-3 text-right font-medium text-slate-800 dark:text-slate-100">{{ number_format($batch->jumlah, 0, ',', '.') }}</td>
                                            <td class="px-5 py-3">
                                                <span @class([
                                                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                                    'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' => $batch->tanggal_expired->isPast(),
                                                    'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => ! $batch->tanggal_expired->isPast(),
                                                ])>
                                                    {{ $batch->tanggal_expired->translatedFormat('d M Y') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            <section class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Penerimaan terakhir</h3>

                    @if ($penerimaanTerakhir->isEmpty())
                        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Belum ada penerimaan tercatat.</p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @foreach ($penerimaanTerakhir as $item)
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 grid h-8 w-8 place-items-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                            {{ $item->nomor_penerimaan ?? 'Penerimaan #'.$item->id }}
                                        </p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ $item->fasilitas?->nama ?? 'Gudang Dinas' }}
                                            &middot; {{ optional($item->tanggal_penerimaan)->translatedFormat('d M Y') }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Aktivitas terbaru</h3>

                    @if ($aktivitas->isEmpty())
                        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Belum ada aktivitas.</p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @foreach ($aktivitas as $log)
                                <li class="flex items-start gap-3">
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-400"></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $log->description }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $log->causer?->name ?? 'Sistem' }}
                                            &middot; {{ $log->created_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
