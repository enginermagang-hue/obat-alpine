@extends('layouts.app')

@section('title', $retur->nomor_retur)
@section('subtitle', 'Detail retur obat')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('retur.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if (in_array($retur->status, ['draft', 'menunggu_approval'], true))
                    @can('update', $retur)
                        <a href="{{ route('retur.edit', $retur) }}" class="btn btn-secondary">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Ubah
                        </a>
                    @endcan
                @endif
                @if ($retur->status === 'draft')
                    <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Ajukan retur', message: @js('Retur dikirim ke approver dan tidak bisa diubah lagi.'), action: '{{ route('retur.submit', $retur) }}', method: 'PATCH', label: 'Ajukan' })">
                        <x-heroicon-o-paper-airplane class="h-4 w-4" />
                        Ajukan
                    </button>
                    @can('delete', $retur)
                        <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus retur', message: @js('Draft akan dihapus permanen.'), action: '{{ route('retur.destroy', $retur) }}', method: 'DELETE', label: 'Hapus' })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Hapus
                        </button>
                    @endcan
                @elseif ($retur->status === 'menunggu_approval')
                    <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Setujui retur', message: @js('Retur disetujui dan siap dikirim.'), action: '{{ route('retur.approve', $retur) }}', method: 'PATCH', label: 'Setujui' })">
                        <x-heroicon-o-check class="h-4 w-4" />
                        Setujui
                    </button>
                    <button type="button" class="btn btn-ghost text-red-600 dark:text-red-400" @click="document.getElementById('form-tolak-retur').classList.toggle('hidden')">
                        Tolak…
                    </button>
                @elseif ($retur->status === 'disetujui')
                    <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Kirim retur', message: @js('Status menjadi dalam pengiriman.'), action: '{{ route('retur.send', $retur) }}', method: 'PATCH', label: 'Kirim' })">
                        <x-heroicon-o-truck class="h-4 w-4" />
                        Kirim
                    </button>
                @elseif ($retur->status === 'dalam_pengiriman')
                    <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Terima retur', message: @js('Stok pengirim berkurang, stok penerima bertambah.'), action: '{{ route('retur.receive', $retur) }}', method: 'PATCH', label: 'Terima' })">
                        <x-heroicon-o-check-circle class="h-4 w-4" />
                        Terima
                    </button>
                @elseif ($retur->status === 'diterima')
                    <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Selesaikan retur', message: @js('Retur ditandai selesai.'), action: '{{ route('retur.complete', $retur) }}', method: 'PATCH', label: 'Selesaikan' })">
                        <x-heroicon-o-check-badge class="h-4 w-4" />
                        Selesaikan
                    </button>
                @endif
                @if (in_array($retur->status, ['disetujui', 'dalam_pengiriman', 'diterima'], true))
                    @can('viewAny', App\Models\InspeksiRetur::class)
                        <a href="{{ route('inspeksi-retur.create', ['retur_id' => $retur->id]) }}" class="btn btn-secondary">
                            <x-heroicon-o-magnifying-glass class="h-4 w-4" />
                            Inspeksi
                        </a>
                    @endcan
                @endif
            </div>
        </section>

        <form id="form-tolak-retur" method="POST" action="{{ route('retur.reject', $retur) }}" class="hidden rounded-2xl border border-red-200 bg-red-50 px-5 py-4 dark:border-red-500/30 dark:bg-red-500/10">
            @csrf
            @method('PATCH')
            <label for="alasan_penolakan" class="form-label">Alasan penolakan *</label>
            <textarea id="alasan_penolakan" name="alasan_penolakan" rows="2" required class="form-textarea"></textarea>
            <div class="mt-2 flex justify-end">
                <button type="submit" class="btn btn-danger-solid btn-sm">Kirim penolakan</button>
            </div>
        </form>

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $retur->nomor_retur }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        {{ $retur->fasilitasPengirim?->nama ?? 'Gudang Dinas' }} &rarr; {{ $retur->fasilitasPenerima?->nama ?? ($retur->supplier?->nama ?? 'Gudang Dinas') }}
                    </h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ str($retur->tipe_retur)->replace('_', ' → ')->title() }}
                        &middot; {{ $retur->tanggal_retur?->translatedFormat('l, d F Y') }}
                    </p>
                    <p class="mt-1 text-sm text-white/70">
                        Alasan: {{ str($retur->alasan)->replace('_', ' ')->title() }}{{ $retur->alasan_lainnya ? ' — '.$retur->alasan_lainnya : '' }}
                    </p>
                    @if ($retur->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $retur->catatan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold capitalize">{{ str_replace('_', ' ', $retur->status) }}</p>
                    <p class="mt-1 text-xs text-white/70">{{ $retur->details->count() }} item</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Item retur</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 font-medium">Batch</th>
                            <th class="px-5 py-3 text-right font-medium">Jumlah</th>
                            <th class="px-5 py-3 font-medium">Bukti</th>
                            <th class="px-5 py-3 font-medium">Inspeksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($retur->details as $detail)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }}</p>
                                    @if ($detail->catatan)
                                        <p class="text-xs text-slate-400">{{ $detail->catatan }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                                    {{ $detail->batch?->batch_number ?? '—' }}
                                    @if ($detail->batch?->tanggal_expired)
                                        <span class="text-slate-400">({{ $detail->batch->tanggal_expired->translatedFormat('d M Y') }})</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($detail->jumlah_retur, 0, ',', '.') }}</td>
                                <td class="px-5 py-3">
                                    @if ($detail->bukti_foto)
                                        <a href="{{ asset('storage/'.$detail->bukti_foto) }}" target="_blank" class="text-xs font-medium text-brand-600 underline dark:text-brand-300">Lihat</a>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    @if ($detail->inspeksi)
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $detail->inspeksi->hasil_inspeksi === 'layak' ? 'Layak' : ($detail->inspeksi->hasil_inspeksi === 'tidak_layak' ? 'Tidak layak' : 'Tindak lanjut') }}</span>
                                        <span class="text-slate-400">· {{ str($detail->inspeksi->tindakan)->replace('_', ' ') }}</span>
                                    @else
                                        <span class="text-slate-400">Belum diinspeksi</span>
                                    @endif
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
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Riwayat muncul setelah retur diterima.</p>
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
