@extends('layouts.app')

@section('title', $rko->nomor_rko)
@section('subtitle', 'Rencana kebutuhan obat')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('rko.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if ($rko->status === 'draft')
                    @can('update', $rko)
                        <a href="{{ route('rko.edit', $rko) }}" class="btn btn-secondary">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Ubah
                        </a>
                        <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Ajukan RKO', message: @js('RKO dikirim untuk persetujuan.'), action: '{{ route('rko.submit', $rko) }}', method: 'PATCH', label: 'Ajukan' })">
                            <x-heroicon-o-paper-airplane class="h-4 w-4" />
                            Ajukan
                        </button>
                    @endcan
                    @can('delete', $rko)
                        <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus RKO', message: @js('Draft akan dihapus permanen.'), action: '{{ route('rko.destroy', $rko) }}', method: 'DELETE', label: 'Hapus' })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Hapus
                        </button>
                    @endcan
                @elseif ($rko->status === 'diajukan')
                    @can('update', $rko)
                        <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Setujui RKO', message: @js('RKO disetujui.'), action: '{{ route('rko.approve', $rko) }}', method: 'PATCH', label: 'Setujui' })">
                            <x-heroicon-o-check class="h-4 w-4" />
                            Setujui
                        </button>
                        <button type="button" class="btn btn-ghost text-red-600 dark:text-red-400" @click="document.getElementById('form-tolak-rko').classList.toggle('hidden')">Tolak…</button>
                    @endcan
                @endif
                <a href="{{ route('rko.cetak-pdf', $rko) }}" target="_blank" class="btn btn-secondary">
                    <x-heroicon-o-printer class="h-4 w-4" />
                    PDF
                </a>
                <a href="{{ route('rko.cetak-xls', $rko) }}" class="btn btn-secondary">
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                    Excel
                </a>
            </div>
        </section>

        <form id="form-tolak-rko" method="POST" action="{{ route('rko.reject', $rko) }}" class="hidden rounded-2xl border border-red-200 bg-red-50 px-5 py-4 dark:border-red-500/30 dark:bg-red-500/10">
            @csrf
            @method('PATCH')
            <label for="catatan" class="form-label">Alasan penolakan *</label>
            <textarea id="catatan" name="catatan" rows="2" required class="form-textarea"></textarea>
            <div class="mt-2 flex justify-end">
                <button type="submit" class="btn btn-danger-solid btn-sm">Kirim penolakan</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $rko->nomor_rko }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $rko->fasilitas?->nama }}</h2>
                    <p class="mt-2 text-sm text-white/80">
                        Periode {{ $rko->periode_tahun }}
                        &middot; Dibuat {{ $rko->tanggal_pembuatan?->translatedFormat('d M Y') }} oleh {{ $rko->dibuatOleh?->name }}
                    </p>
                    @if ($rko->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $rko->catatan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold capitalize">{{ $rko->status }}</p>
                    <p class="mt-1 text-xs text-white/70">Total: Rp {{ number_format((float) $rko->total_anggaran, 0, ',', '.') }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Detail ({{ $rko->details->count() }})</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 text-right font-medium">Pemakaian thn lalu</th>
                            <th class="px-5 py-3 text-right font-medium">Rata2/bln</th>
                            <th class="px-5 py-3 text-right font-medium">Stok</th>
                            <th class="px-5 py-3 text-right font-medium">Usulan</th>
                            <th class="px-5 py-3 text-right font-medium">Total kebutuhan</th>
                            <th class="px-5 py-3 text-right font-medium">Total harga</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($rko->details as $detail)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }}{{ $detail->ven_kategori ? ' · VEN '.$detail->ven_kategori : '' }}{{ $detail->abc_kategori ? ' · ABC '.$detail->abc_kategori : '' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($detail->pemakaian_tahun_sebelumnya, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($detail->rata_rata_pemakaian_bulanan, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($detail->stok_akhir, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($detail->usulan, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ number_format($detail->total_kebutuhan, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ $detail->total_harga ? 'Rp '.number_format($detail->total_harga, 0, ',', '.') : '—' }}</td>
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
