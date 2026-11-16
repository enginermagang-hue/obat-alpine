@extends('layouts.app')

@section('title', $neraca->nomor_neraca)
@section('subtitle', 'Detail neraca tahunan')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('neraca.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if ($neraca->status === 'draft')
                    @can('update', $neraca)
                        <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Selesaikan neraca', message: @js('Neraca dikunci dan tidak bisa diubah lagi.'), action: '{{ route('neraca.finish', $neraca) }}', method: 'PATCH', label: 'Selesaikan' })">
                            <x-heroicon-o-check class="h-4 w-4" />
                            Tandai selesai
                        </button>
                    @endcan
                    @can('delete', $neraca)
                        <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus neraca', message: @js('Draft akan dihapus permanen.'), action: '{{ route('neraca.destroy', $neraca) }}', method: 'DELETE', label: 'Hapus' })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Hapus
                        </button>
                    @endcan
                @else
                    @can('update', $neraca)
                        <button type="button" class="btn btn-secondary" @click="openConfirm({ title: 'Buka kembali neraca', message: @js('Neraca kembali ke draft.'), action: '{{ route('neraca.reopen', $neraca) }}', method: 'PATCH', label: 'Buka kembali' })">
                            <x-heroicon-o-lock-open class="h-4 w-4" />
                            Buka kembali
                        </button>
                    @endcan
                @endif
                <a href="{{ route('neraca.cetak-pdf', $neraca) }}" target="_blank" class="btn btn-secondary">
                    <x-heroicon-o-printer class="h-4 w-4" />
                    PDF
                </a>
                <a href="{{ route('neraca.cetak-xls', $neraca) }}" class="btn btn-secondary">
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                    Excel
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $neraca->nomor_neraca }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $neraca->fasilitas?->nama ?? 'Gudang Dinas' }}</h2>
                    <p class="mt-2 text-sm text-white/80">
                        Tahun {{ $neraca->tahun }}
                        &middot; Dibuat {{ $neraca->created_at?->translatedFormat('d M Y') }} oleh {{ $neraca->dibuatOleh?->name }}
                    </p>
                    @if ($neraca->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $neraca->catatan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold capitalize">{{ $neraca->status }}</p>
                    <p class="mt-1 text-xs text-white/70">Nilai: Rp {{ number_format($neraca->details->sum('nilai_stok'), 0, ',', '.') }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Detail per obat ({{ $neraca->details->count() }})</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 text-right font-medium">Stok awal</th>
                            <th class="px-5 py-3 text-right font-medium">Masuk</th>
                            <th class="px-5 py-3 text-right font-medium">Keluar</th>
                            <th class="px-5 py-3 text-right font-medium">Stok akhir</th>
                            <th class="px-5 py-3 text-right font-medium">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($neraca->details as $detail)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }} &middot; {{ $detail->obat?->satuan }}</p>
                                    @if ($detail->sumberDanaDetails->isNotEmpty())
                                        <div class="mt-1 space-y-0.5">
                                            @foreach ($detail->sumberDanaDetails as $sd)
                                                <p class="text-xs text-slate-400">{{ $sd->sumberDana?->nama }}: {{ number_format($sd->stok_akhir_jumlah, 0, ',', '.') }}</p>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($detail->stok_awal, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($detail->total_masuk, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($detail->total_keluar, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($detail->stok_akhir, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ $detail->nilai_stok ? 'Rp '.number_format($detail->nilai_stok, 0, ',', '.') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <x-ui.modal name="confirm">
            <form :action="confirm.action" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="confirm.method ?? 'PATCH'">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="confirm.title"></h2>
                <p class="text-sm text-slate-600 dark:text-slate-300" x-text="confirm.message"></p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost" @click="confirmOpen = false">Batal</button>
                    <button type="submit" class="btn btn-primary" x-text="confirm.label ?? 'Ya'"></button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
