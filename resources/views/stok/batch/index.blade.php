@extends('layouts.app')

@section('title', 'Batch')
@section('subtitle', 'Batch stok per obat dan fasilitas')

@section('content')
    <div class="space-y-6">
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
            <form method="GET" action="{{ route('batch.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari batch, kode, atau nama obat…" class="form-input">
                </div>

                @unless ($lockedFaskes)
                    <select name="fasilitas" class="form-select">
                        <option value="">Semua faskes + gudang</option>
                        <option value="gudang" @selected(($filters['fasilitas'] ?? '') === 'gudang')>Gudang Dinas</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}" @selected((string) ($filters['fasilitas'] ?? '') === (string) $f->id)>{{ $f->nama }}</option>
                        @endforeach
                    </select>
                @endunless

                <select name="status" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    @foreach ($statusList as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>

                <select name="expiry" data-tomselect-search="false" class="form-select">
                    <option value="">Semua kedaluwarsa</option>
                    <option value="d90" @selected(($filters['expiry'] ?? '') === 'd90')>&lt; 90 hari</option>
                    <option value="d30" @selected(($filters['expiry'] ?? '') === 'd30')>&lt; 30 hari</option>
                    <option value="lewat" @selected(($filters['expiry'] ?? '') === 'lewat')>Sudah lewat</option>
                </select>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a href="{{ route('batch.index') }}" class="btn btn-ghost">Atur ulang</a>
                </div>
            </form>

            @if ($batches->isEmpty())
                <x-ui.empty-state title="Belum ada batch" hint="Batch muncul setelah ada penerimaan atau distribusi." icon="queue-list" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Obat</th>
                                <th class="px-5 py-3 font-medium">Batch</th>
                                @unless ($lockedFaskes)
                                    <th class="px-5 py-3 font-medium">Faskes</th>
                                @endunless
                                <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                                <th class="px-5 py-3 font-medium">Kedaluwarsa</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 text-right font-medium">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($batches as $batch)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $batch->obat?->nama_obat }}</p>
                                        <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $batch->obat?->kode_obat }} &middot; {{ $batch->obat?->satuan }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $batch->batch_number }}</td>
                                    @unless ($lockedFaskes)
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $batch->fasilitas?->nama ?? 'Gudang Dinas' }}</td>
                                    @endunless
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($batch->jumlah, 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <x-ui.badge :tone="$batch->tanggal_expired->isPast() ? 'red' : ($batch->tanggal_expired->isBefore(now()->addDays(90)) ? 'amber' : 'slate')">
                                            {{ $batch->tanggal_expired->translatedFormat('d M Y') }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$batch->status === 'tersedia' ? 'emerald' : ($batch->status === 'karantina' ? 'amber' : 'slate')">
                                            {{ ucfirst($batch->status) }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">
                                        {{ $batch->harga_beli ? 'Rp '.number_format($batch->jumlah * $batch->harga_beli, 0, ',', '.') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.pagination :paginator="$batches" />
            @endif
        </section>
    </div>
@endsection
