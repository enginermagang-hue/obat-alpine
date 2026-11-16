@extends('layouts.app')

@section('title', $obat->nama_obat)
@section('subtitle', 'Rincian batch Gudang Dinas')

@section('content')
    <div class="space-y-6">
        <section>
            <a href="{{ route('stok-gudang.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke Stok Gudang
            </a>
        </section>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $obat->kode_obat }} &middot; {{ $obat->kategori }} &middot; {{ $obat->satuan }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $obat->nama_obat }}</h2>
                    <p class="mt-2 text-sm text-white/80">{{ $obat->nama_generik ?? '—' }}</p>
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Total stok</p>
                    <p class="text-xl font-semibold">{{ number_format($stok->jumlah ?? 0, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-white/70">Nilai: Rp {{ number_format($nilai, 0, ',', '.') }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Batch di gudang ({{ $batches->total() }})</h3>
            </div>

            @if ($batches->isEmpty())
                <x-ui.empty-state title="Tidak ada batch" hint="Batch muncul setelah ada penerimaan ke gudang." icon="queue-list" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Batch</th>
                                <th class="px-5 py-3 font-medium">Kedaluwarsa</th>
                                <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 text-right font-medium">Harga beli</th>
                                <th class="px-5 py-3 font-medium">Sumber dana</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($batches as $batch)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $batch->batch_number }}</td>
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <x-ui.badge :tone="$batch->tanggal_expired->isPast() ? 'red' : ($batch->tanggal_expired->isBefore(now()->addDays(90)) ? 'amber' : 'slate')">
                                            {{ $batch->tanggal_expired->translatedFormat('d M Y') }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($batch->jumlah, 0, ',', '.') }}</td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ ucfirst($batch->status) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ $batch->harga_beli ? 'Rp '.number_format($batch->harga_beli, 0, ',', '.') : '—' }}</td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $batch->sumberDana?->nama ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 dark:border-slate-800">
                    {{ $batches->withQueryString()->links() }}
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Riwayat terakhir</h3>
            @if ($riwayat->isEmpty())
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Belum ada riwayat.</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($riwayat as $log)
                        <li class="flex items-start gap-3">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ str_starts_with($log->tipe, 'masuk') || str_contains($log->tipe, 'masuk') ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $log->keterangan ?? $log->tipe }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $log->jumlah > 0 ? '+' : '' }}{{ number_format($log->jumlah, 0, ',', '.') }}
                                    &middot; {{ $log->tanggal?->translatedFormat('d M Y') }}
                                    &middot; {{ $log->user?->name ?? 'Sistem' }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
