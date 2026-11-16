@extends('layouts.app')

@section('title', 'Inspeksi #'.$inspeksi->id)
@section('subtitle', 'Hasil inspeksi retur (tidak mengubah stok)')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('inspeksi-retur.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $inspeksi)
                    <a href="{{ route('inspeksi-retur.edit', $inspeksi) }}" class="btn btn-secondary">
                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                        Ubah
                    </a>
                @endcan
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">Retur: {{ $inspeksi->retur?->nomor_retur }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        Batch {{ $inspeksi->batch?->batch_number }}
                    </h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ $inspeksi->detailRetur?->obat?->nama_obat }}
                        &middot; {{ $inspeksi->tanggal_inspeksi?->translatedFormat('l, d F Y') }}
                        &middot; {{ $inspeksi->inspector?->name }}
                    </p>
                    @if ($inspeksi->catatan_inspeksi)
                        <p class="mt-1 text-sm text-white/70">{{ $inspeksi->catatan_inspeksi }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Hasil</p>
                    <p class="text-xl font-semibold">{{ $inspeksi->hasil_inspeksi === 'layak' ? 'Layak' : ($inspeksi->hasil_inspeksi === 'tidak_layak' ? 'Tidak layak' : 'Tindak lanjut') }}</p>
                    <p class="mt-1 text-xs text-white/70">{{ str($inspeksi->tindakan)->replace('_', ' ')->title() }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="divide-y divide-slate-100 px-5 py-2 text-sm dark:divide-slate-800">
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="text-slate-500 dark:text-slate-400">Retur</dt>
                    <dd class="sm:col-span-2">
                        <a href="{{ route('retur.show', $inspeksi->retur) }}" class="font-mono text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">
                            {{ $inspeksi->retur?->nomor_retur }}
                        </a>
                    </dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="text-slate-500 dark:text-slate-400">Item retur</dt>
                    <dd class="sm:col-span-2 text-slate-800 dark:text-slate-100">
                        {{ $inspeksi->detailRetur?->obat?->nama_obat }} — {{ number_format($inspeksi->detailRetur?->jumlah_retur ?? 0, 0, ',', '.') }} unit
                    </dd>
                </div>
                <div class="grid gap-1 py-3 sm:grid-cols-3">
                    <dt class="text-slate-500 dark:text-slate-400">Tindakan</dt>
                    <dd class="sm:col-span-2 text-slate-800 dark:text-slate-100">{{ str($inspeksi->tindakan)->replace('_', ' ')->title() }}</dd>
                </div>
            </dl>
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
