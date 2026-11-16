@extends('layouts.app')

@section('title', $distribusi->nomor_surat_jalan)
@section('subtitle', 'Detail distribusi obat')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('distribusi.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if ($distribusi->status === 'draft')
                    @can('update', $distribusi)
                        <a href="{{ route('distribusi.edit', $distribusi) }}" class="btn btn-secondary">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Ubah
                        </a>
                        <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Kirim distribusi', message: @js('Status menjadi dalam pengiriman dan permintaan terkait menjadi sedang didistribusi.'), action: '{{ route('distribusi.kirim', $distribusi) }}', method: 'PATCH', label: 'Kirim' })">
                            <x-heroicon-o-paper-airplane class="h-4 w-4" />
                            Kirim
                        </button>
                    @endcan
                    @can('delete', $distribusi)
                        <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus distribusi', message: @js('Draft akan dihapus permanen.'), action: '{{ route('distribusi.destroy', $distribusi) }}', method: 'DELETE', label: 'Hapus' })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Hapus
                        </button>
                    @endcan
                @elseif ($distribusi->status === 'dalam_pengiriman')
                    @can('update', $distribusi)
                        <button type="button" class="btn btn-secondary" @click="openConfirm({ title: 'Batalkan pengiriman', message: @js('Status kembali ke draft.'), action: '{{ route('distribusi.batalkan-pengiriman', $distribusi) }}', method: 'PATCH', label: 'Batalkan' })">
                            <x-heroicon-o-arrow-uturn-left class="h-4 w-4" />
                            Batalkan pengiriman
                        </button>
                    @endcan
                    <button type="button" class="btn btn-secondary" @click="document.getElementById('form-tolak-distribusi').classList.toggle('hidden')">
                        Tolak…
                    </button>
                    <form method="POST" action="{{ route('distribusi.buat-penerimaan', $distribusi) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                            Buat Penerimaan
                        </button>
                    </form>
                @endif
            </div>
        </section>

        <form id="form-tolak-distribusi" method="POST" action="{{ route('distribusi.tolak', $distribusi) }}" class="hidden rounded-2xl border border-red-200 bg-red-50 px-5 py-4 dark:border-red-500/30 dark:bg-red-500/10">
            @csrf
            @method('PATCH')
            <label for="alasan" class="form-label">Alasan penolakan *</label>
            <textarea id="alasan" name="alasan" rows="2" required class="form-textarea"></textarea>
            @error('alasan')<p class="form-error">{{ $message }}</p>@enderror
            <div class="mt-2 flex justify-end">
                <button type="submit" class="btn btn-danger-solid btn-sm">Kirim penolakan</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $distribusi->nomor_surat_jalan }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        {{ $distribusi->fasilitasPengirim?->nama ?? 'Gudang Dinas' }} &rarr; {{ $distribusi->fasilitasPenerima?->nama }}
                    </h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ $distribusi->tipe_distribusi === 'dinas_ke_puskesmas' ? 'Dinas → Puskesmas' : 'Puskesmas → Pustu' }}
                        &middot; {{ $distribusi->tanggal_kirim?->translatedFormat('l, d F Y') ?? 'Tanggal kirim belum diisi' }}
                        &middot; {{ $distribusi->pengirim?->name }}
                    </p>
                    @if ($distribusi->permintaan)
                        <p class="mt-1 text-sm text-white/70">
                            Permintaan: <a href="{{ route('permintaan.show', $distribusi->permintaan) }}" class="underline">{{ $distribusi->permintaan->nomor_permintaan }}</a>
                        </p>
                    @endif
                    @if ($distribusi->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $distribusi->catatan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold">{{ str($distribusi->status)->replace('_', ' ')->title() }}</p>
                    <p class="mt-1 text-xs text-white/70">{{ $distribusi->details->count() }} baris</p>
                </div>
            </div>
        </section>

        @if ($distribusi->penerimaanStok)
            <section class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-slate-600 dark:text-slate-300">
                    Penerimaan terkait:
                    <a href="{{ route('penerimaan.show', $distribusi->penerimaanStok) }}" class="font-mono font-medium text-brand-700 hover:underline dark:text-brand-300">
                        {{ $distribusi->penerimaanStok->nomor_penerimaan }}
                    </a>
                    <span class="text-xs text-slate-500">· {{ ucfirst($distribusi->penerimaanStok->status) }}</span>
                </p>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Item ({{ $distribusi->details->count() }})</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 font-medium">Batch</th>
                            <th class="px-5 py-3 font-medium">Kedaluwarsa</th>
                            <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($distribusi->details as $detail)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $detail->batch?->batch_number ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $detail->batch?->tanggal_expired?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
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
