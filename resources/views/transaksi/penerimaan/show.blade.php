@extends('layouts.app')

@section('title', $penerimaan->nomor_penerimaan)
@section('subtitle', 'Detail penerimaan stok')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('penerimaan.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if ($penerimaan->status === 'draft')
                    @can('update', $penerimaan)
                        <a href="{{ route('penerimaan.edit', $penerimaan) }}" class="btn btn-secondary">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Ubah
                        </a>
                        <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Konfirmasi penerimaan', message: @js('Stok akan bertambah sesuai item. Batch baru dibuat.'), action: '{{ route('penerimaan.confirm', $penerimaan) }}', method: 'PATCH', label: 'Konfirmasi' })">
                            <x-heroicon-o-check class="h-4 w-4" />
                            Konfirmasi
                        </button>
                    @endcan
                    @can('delete', $penerimaan)
                        <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus penerimaan', message: @js('Draft akan dihapus permanen.'), action: '{{ route('penerimaan.destroy', $penerimaan) }}', method: 'DELETE', label: 'Hapus' })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Hapus
                        </button>
                    @endcan
                @elseif ($penerimaan->status === 'dikonfirmasi')
                    @can('update', $penerimaan)
                        <button type="button" class="btn btn-secondary" @click="openConfirm({ title: 'Batalkan penerimaan', message: @js('Stok yang sudah masuk akan dikembalikan.'), action: '{{ route('penerimaan.cancel', $penerimaan) }}', method: 'PATCH', label: 'Batalkan' })">
                            <x-heroicon-o-arrow-uturn-left class="h-4 w-4" />
                            Batalkan
                        </button>
                    @endcan
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $penerimaan->nomor_penerimaan }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        Penerimaan {{ ucfirst($penerimaan->tipe) }}
                    </h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ $penerimaan->fasilitas?->nama ?? 'Gudang Dinas' }}
                        &middot; {{ $penerimaan->tanggal_penerimaan?->translatedFormat('l, d F Y') }}
                        &middot; {{ $penerimaan->user?->name }}
                    </p>
                    <p class="mt-1 text-sm text-white/70">
                        {{ $penerimaan->supplier?->nama ? 'Supplier: '.$penerimaan->supplier->nama.' · ' : '' }}{{ $penerimaan->sumberDana ? 'Dana: '.$penerimaan->sumberDana->nama.' · ' : '' }}{{ $penerimaan->nomor_po ? 'PO: '.$penerimaan->nomor_po.' · ' : '' }}{{ $penerimaan->nomor_invoice ? 'Invoice: '.$penerimaan->nomor_invoice : '' }}
                    </p>
                    @if ($penerimaan->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $penerimaan->catatan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold capitalize">{{ $penerimaan->status === 'dikonfirmasi' ? 'Dikonfirmasi' : ucfirst($penerimaan->status) }}</p>
                    <p class="mt-1 text-xs text-white/70">Total: Rp {{ number_format((float) $penerimaan->total_biaya, 0, ',', '.') }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Item ({{ $penerimaan->details->count() }})</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 font-medium">Batch</th>
                            <th class="px-5 py-3 font-medium">Kedaluwarsa</th>
                            <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                            <th class="px-5 py-3 text-right font-medium">Harga</th>
                            <th class="px-5 py-3 text-right font-medium">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($penerimaan->details as $detail)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $detail->batch_number ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $detail->tanggal_expired?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($detail->jumlah, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ $detail->harga_satuan ? 'Rp '.number_format($detail->harga_satuan, 0, ',', '.') : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ $detail->sub_total ? 'Rp '.number_format($detail->sub_total, 0, ',', '.') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Riwayat yang dihasilkan</h3>
            @if ($riwayat->isEmpty())
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                    {{ $penerimaan->status === 'dikonfirmasi' ? 'Tidak ada mutasi tercatat.' : 'Riwayat muncul setelah penerimaan dikonfirmasi.' }}
                </p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($riwayat as $log)
                        <li class="flex items-start gap-3">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-400"></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $log->obat?->nama_obat }} — {{ $log->keterangan }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    +{{ number_format($log->jumlah, 0, ',', '.') }}
                                    &middot; {{ $log->tanggal?->translatedFormat('d M Y') }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
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
