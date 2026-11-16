@extends('layouts.app')

@section('title', $opname->nomor_opname)
@section('subtitle', 'Detail stok opname')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('opname.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if ($opname->status !== 'selesai')
                    @can('update', $opname)
                        <a href="{{ route('opname.edit', $opname) }}" class="btn btn-secondary">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Ubah
                        </a>
                    @endcan
                    @can('delete', $opname)
                        @if ($opname->status === 'draft')
                            <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus opname', message: @js('Opname draft akan dihapus permanen.'), action: '{{ route('opname.destroy', $opname) }}', method: 'DELETE', label: 'Hapus' })">
                                <x-heroicon-o-trash class="h-4 w-4" />
                                Hapus
                            </button>
                        @endif
                    @endcan
                @else
                    @can('update', $opname)
                        <button type="button" class="btn btn-secondary" @click="openConfirm({ title: 'Buka kembali opname', message: @js('Status kembali ke draft dan efek stok dibatalkan.'), action: '{{ route('opname.reopen', $opname) }}', method: 'PATCH', label: 'Buka kembali' })">
                            <x-heroicon-o-lock-open class="h-4 w-4" />
                            Buka kembali
                        </button>
                    @endcan
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $opname->nomor_opname }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        {{ $opname->tipe === 'penyesuaian' ? 'Penyesuaian' : ($opname->tipe === 'stok_awal' ? 'Stok awal' : 'Stok baru') }}
                    </h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ $opname->fasilitas?->nama ?? 'Gudang Dinas' }}
                        &middot; {{ $opname->tanggal_opname?->translatedFormat('l, d F Y') }}
                        &middot; {{ $opname->user?->name }}
                    </p>
                    @if ($opname->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $opname->catatan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold">{{ ucfirst($opname->status) }}</p>
                    <p class="mt-1 text-xs text-white/70">{{ $opname->details->count() }} item</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Item opname</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 font-medium">Batch</th>
                            <th class="px-5 py-3 text-right font-medium">Sistem</th>
                            <th class="px-5 py-3 text-right font-medium">Fisik</th>
                            <th class="px-5 py-3 text-right font-medium">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($opname->details as $detail)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                                    {{ $detail->batch_number ?? $detail->batch?->batch_number ?? '—' }}
                                    @if ($detail->tanggal_expired)
                                        <span class="text-slate-400">({{ $detail->tanggal_expired->translatedFormat('d M Y') }})</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($detail->stok_sistem, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ number_format($detail->stok_fisik, 0, ',', '.') }}</td>
                                <td @class([
                                    'whitespace-nowrap px-5 py-3 text-right font-semibold',
                                    'text-slate-400' => $detail->selisih === 0,
                                    'text-emerald-600 dark:text-emerald-400' => $detail->selisih > 0,
                                    'text-red-600 dark:text-red-400' => $detail->selisih < 0,
                                ])>
                                    {{ $detail->selisih > 0 ? '+' : '' }}{{ number_format($detail->selisih, 0, ',', '.') }}
                                </td>
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
                    {{ $opname->status === 'selesai' ? 'Tidak ada perubahan stok (semua selisih nol).' : 'Riwayat muncul setelah opname diselesaikan.' }}
                </p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($riwayat as $log)
                        <li class="flex items-start gap-3">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $log->jumlah >= 0 ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm text-slate-700 dark:text-slate-200">{{ $log->obat?->nama_obat }} — {{ $log->keterangan }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $log->jumlah > 0 ? '+' : '' }}{{ number_format($log->jumlah, 0, ',', '.') }}
                                    &middot; {{ $log->fasilitas?->nama ?? 'Gudang Dinas' }}
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
                <input type="hidden" name="_method" :value="confirm.method ?? 'DELETE'">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="confirm.title"></h2>
                <p class="text-sm text-slate-600 dark:text-slate-300" x-text="confirm.message"></p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="btn btn-ghost" @click="confirmOpen = false">Batal</button>
                    <button type="submit" class="btn btn-danger-solid" x-text="confirm.label ?? 'Hapus'"></button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
