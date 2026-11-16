@extends('layouts.app')

@section('title', $laporan->nomor_laporan)
@section('subtitle', 'Detail LPLPO')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('lplpo.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if ($laporan->status === 'draft')
                    @can('update', $laporan)
                        <a href="{{ route('lplpo.edit', $laporan) }}" class="btn btn-secondary">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Ubah
                        </a>
                        <button type="button" class="btn btn-secondary" @click="openConfirm({ title: 'Generate ulang detail', message: @js('Semua baris akan dihapus dan dibuat ulang dari riwayat stok.'), action: '{{ route('lplpo.regenerate', $laporan) }}', method: 'PATCH', label: 'Generate ulang' })">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            Generate ulang
                        </button>
                        <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Ajukan LPLPO', message: @js('LPLPO dikirim untuk verifikasi dinas.'), action: '{{ route('lplpo.submit', $laporan) }}', method: 'PATCH', label: 'Ajukan' })">
                            <x-heroicon-o-paper-airplane class="h-4 w-4" />
                            Ajukan
                        </button>
                    @endcan
                    @can('delete', $laporan)
                        <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus LPLPO', message: @js('Draft akan dihapus permanen.'), action: '{{ route('lplpo.destroy', $laporan) }}', method: 'DELETE', label: 'Hapus' })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Hapus
                        </button>
                    @endcan
                @endif
                @if ($laporan->status_verifikasi === 'menunggu_verifikasi')
                    @can('verify', $laporan)
                        <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Verifikasi LPLPO', message: @js('LPLPO ditandai sudah diverifikasi.'), action: '{{ route('lplpo.verify', $laporan) }}', method: 'PATCH', label: 'Verifikasi' })">
                            <x-heroicon-o-check-badge class="h-4 w-4" />
                            Verifikasi
                        </button>
                        <button type="button" class="btn btn-ghost text-red-600 dark:text-red-400" @click="document.getElementById('form-revisi').classList.toggle('hidden')">Minta revisi…</button>
                    @endcan
                @endif
                @can('create', App\Models\LaporanLplpo::class)
                    <button type="button" class="btn btn-secondary" @click="openConfirm({ title: 'Buat revisi', message: @js('Buat LPLPO draft baru sebagai revisi dari dokumen ini.'), action: '{{ route('lplpo.revise', $laporan) }}', method: 'POST', label: 'Buat revisi' })">
                        <x-heroicon-o-document-duplicate class="h-4 w-4" />
                        Buat revisi
                    </button>
                @endcan
                <a href="{{ route('lplpo.cetak', $laporan) }}" target="_blank" class="btn btn-secondary">
                    <x-heroicon-o-printer class="h-4 w-4" />
                    Cetak PDF
                </a>
            </div>
        </section>

        @if ($laporan->status_verifikasi === 'menunggu_revisi' && $laporan->catatan_verifikator)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <p class="font-medium">Diminta revisi:</p>
                <p class="mt-1">{{ $laporan->catatan_verifikator }}</p>
            </section>
        @endif

        <form id="form-revisi" method="POST" action="{{ route('lplpo.request-revision', $laporan) }}" class="hidden rounded-2xl border border-red-200 bg-red-50 px-5 py-4 dark:border-red-500/30 dark:bg-red-500/10">
            @csrf
            @method('PATCH')
            <label for="catatan_verifikator" class="form-label">Catatan revisi *</label>
            <textarea id="catatan_verifikator" name="catatan_verifikator" rows="2" required class="form-textarea"></textarea>
            <div class="mt-2 flex justify-end">
                <button type="submit" class="btn btn-danger-solid btn-sm">Kirim permintaan revisi</button>
            </div>
        </form>

        @if (! empty($peringatan))
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <p class="font-medium">Peringatan validasi:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($peringatan as $w)
                        <li>{{ is_string($w) ? $w : json_encode($w) }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $laporan->nomor_laporan }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $laporan->fasilitas?->nama }}</h2>
                    <p class="mt-2 text-sm text-white/80">
                        Periode {{ \Carbon\Carbon::create(null, $laporan->periode_bulan)->translatedFormat('F') }} {{ $laporan->periode_tahun }}
                        &middot; Dibuat {{ $laporan->tanggal_pembuatan?->translatedFormat('d M Y') }} oleh {{ $laporan->dibuatOleh?->name }}
                    </p>
                    @if ($laporan->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $laporan->catatan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold capitalize">{{ $laporan->status }}</p>
                    <p class="mt-1 text-xs text-white/70">{{ $laporan->labelStatusVerifikasi() }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Detail ({{ $laporan->details->count() }})</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Obat</th>
                            <th class="px-5 py-3 text-right font-medium">Stok awal</th>
                            <th class="px-5 py-3 text-right font-medium">Masuk</th>
                            <th class="px-5 py-3 text-right font-medium">Keluar</th>
                            <th class="px-5 py-3 text-right font-medium">Sisa</th>
                            <th class="px-5 py-3 text-right font-medium">Optimum</th>
                            <th class="px-5 py-3 text-right font-medium">Permintaan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($laporan->details as $detail)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }} &middot; {{ $detail->obat?->satuan }}</p>
                                </td>
                                @foreach (['stok_awal', 'jumlah_masuk', 'jumlah_keluar', 'sisa_stok'] as $col)
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ number_format($detail->$col, 0, ',', '.') }}</td>
                                @endforeach
                                <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ $detail->stok_optimum !== null ? number_format($detail->stok_optimum, 0, ',', '.') : '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-semibold text-slate-800 dark:text-slate-100">{{ number_format($detail->permintaan_selanjutnya, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($laporan->revisiLplpo->isNotEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Revisi dari dokumen ini</h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($laporan->revisiLplpo as $rev)
                        <li>
                            <a href="{{ route('lplpo.show', $rev) }}" class="font-mono text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">{{ $rev->nomor_laporan }}</a>
                            <span class="text-xs text-slate-500">· {{ ucfirst($rev->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

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
