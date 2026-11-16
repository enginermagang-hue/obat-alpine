@extends('layouts.app')

@section('title', $permintaan->nomor_permintaan)
@section('subtitle', 'Detail permintaan obat')

@section('content')
    <div class="space-y-6" x-data="modal()">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('permintaan.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali ke daftar
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if (in_array($permintaan->status, ['draft', 'menunggu_persetujuan', 'ditolak'], true))
                    @can('update', $permintaan)
                        <a href="{{ route('permintaan.edit', $permintaan) }}" class="btn btn-secondary">
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Ubah
                        </a>
                    @endcan
                @endif
                @if ($permintaan->status === 'draft')
                    @can('update', $permintaan)
                        @if ($permintaan->fasilitas_pengirim_id === auth()->user()->fasilitas_kesehatan_id)
                            <button type="button" class="btn btn-primary" @click="openConfirm({ title: 'Ajukan permintaan', message: @js('Permintaan dikirim ke approver dan tidak bisa diubah lagi sebelum diproses.'), action: '{{ route('permintaan.submit', $permintaan) }}', method: 'PATCH', label: 'Ajukan' })">
                                <x-heroicon-o-paper-airplane class="h-4 w-4" />
                                Ajukan
                            </button>
                        @endif
                    @endcan
                    @can('delete', $permintaan)
                        <button type="button" class="btn btn-danger-solid" @click="openConfirm({ title: 'Hapus permintaan', message: @js('Draft akan dihapus permanen.'), action: '{{ route('permintaan.destroy', $permintaan) }}', method: 'DELETE', label: 'Hapus' })">
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Hapus
                        </button>
                    @endcan
                @endif
                @if ($permintaan->status === 'disetujui' && ! $permintaan->distribusi()->exists())
                    @can('create', App\Models\DistribusiObat::class)
                        <a href="{{ route('distribusi.create', ['permintaan_id' => $permintaan->id]) }}" class="btn btn-primary">
                            <x-heroicon-o-truck class="h-4 w-4" />
                            Buat Distribusi
                        </a>
                    @endcan
                @endif
            </div>
        </section>

        @if ($permintaan->status === 'ditolak' && $permintaan->alasan_penolakan)
            <section class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                <p class="font-medium">Ditolak dengan alasan:</p>
                <p class="mt-1">{{ $permintaan->alasan_penolakan }}</p>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs text-white/70">{{ $permintaan->nomor_permintaan }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        {{ $permintaan->fasilitasPengirim?->nama }} &rarr; {{ $permintaan->fasilitasTujuan?->nama ?? 'Gudang Dinas' }}
                    </h2>
                    <p class="mt-2 text-sm text-white/80">
                        {{ $permintaan->tipe_permintaan === 'pustu_ke_puskesmas' ? 'Pustu → Puskesmas' : 'Puskesmas → Dinas' }}
                        &middot; {{ $permintaan->tanggal_permintaan?->translatedFormat('l, d F Y') }}
                    </p>
                    @if ($permintaan->catatan)
                        <p class="mt-1 text-sm text-white/70">{{ $permintaan->catatan }}</p>
                    @endif
                    @if ($permintaan->surat_permintaan)
                        <p class="mt-1 text-sm text-white/70">Surat: {{ $permintaan->surat_permintaan }}</p>
                    @endif
                </div>
                <div class="rounded-xl bg-white/10 px-4 py-3 text-sm ring-1 ring-white/20">
                    <p class="text-white/70">Status</p>
                    <p class="text-xl font-semibold">{{ str($permintaan->status)->replace('_', ' ')->title() }}</p>
                    <p class="mt-1 text-xs text-white/70">
                        @if ($permintaan->disetujuiOleh)
                            {{ $permintaan->tanggal_disetujui?->translatedFormat('d M Y') }} &middot; {{ $permintaan->disetujuiOleh->name }}
                        @else
                            {{ $permintaan->details->count() }} item
                        @endif
                    </p>
                </div>
            </div>
        </section>

        @if ($permintaan->status === 'menunggu_persetujuan' && $bisaSetujui)
            <section class="rounded-2xl border border-amber-200 bg-amber-50/50 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/5">
                <form method="POST" action="{{ route('permintaan.approve', $permintaan) }}" class="px-5 py-5">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Tinjau permintaan</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sesuaikan jumlah disetujui per baris (kosongkan = setujui sesuai permintaan), lalu setujui atau tolak.</p>

                    <div class="mt-4 space-y-3">
                        @foreach ($permintaan->details as $detail)
                            <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-12 dark:border-slate-700 dark:bg-slate-900">
                                <div class="sm:col-span-6">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }} &middot; diminta {{ number_format($detail->jumlah_diminta, 0, ',', '.') }}</p>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="setuju-{{ $detail->id }}" class="form-label">Disetujui</label>
                                    <input id="setuju-{{ $detail->id }}" type="number" min="0" name="jumlah_disetujui[{{ $detail->id }}]" value="{{ old('jumlah_disetujui.'.$detail->id, $detail->jumlah_disetujui ?? $detail->jumlah_diminta) }}" class="form-input text-right">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="form-label">Diterima (info)</label>
                                    <input type="number" value="{{ $detail->jumlah_diterima ?? 0 }}" disabled class="form-input text-right">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-red-600 dark:text-red-400"
                            @click="document.getElementById('form-tolak').classList.toggle('hidden'); document.getElementById('form-tolak').scrollIntoView({ behavior: 'smooth', block: 'nearest' })">
                            Tolak…
                        </button>
                        <button type="submit" class="btn btn-primary">Setujui</button>
                    </div>
                </form>

                <form id="form-tolak" method="POST" action="{{ route('permintaan.reject', $permintaan) }}" class="hidden px-5 pb-5">
                    @csrf
                    @method('PATCH')
                    <label for="alasan_penolakan" class="form-label">Alasan penolakan *</label>
                    <textarea id="alasan_penolakan" name="alasan_penolakan" rows="2" required class="form-textarea"></textarea>
                    @error('alasan_penolakan')<p class="form-error">{{ $message }}</p>@enderror
                    <div class="mt-2 flex justify-end">
                        <button type="submit" class="btn btn-danger-solid btn-sm">Kirim penolakan</button>
                    </div>
                </form>
            </section>
        @else
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Item ({{ $permintaan->details->count() }})</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Obat</th>
                                <th class="px-5 py-3 text-right font-medium">Diminta</th>
                                <th class="px-5 py-3 text-right font-medium">Disetujui</th>
                                <th class="px-5 py-3 text-right font-medium">Dikirim</th>
                                <th class="px-5 py-3 text-right font-medium">Diterima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($permintaan->details as $detail)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $detail->obat?->nama_obat }}</p>
                                        <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $detail->obat?->kode_obat }}</p>
                                        @if ($detail->catatan)
                                            <p class="text-xs text-slate-400">{{ $detail->catatan }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ number_format($detail->jumlah_diminta, 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-800 dark:text-slate-100">{{ $detail->jumlah_disetujui !== null ? number_format($detail->jumlah_disetujui, 0, ',', '.') : '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ $detail->jumlah_dikirim ? number_format($detail->jumlah_dikirim, 0, ',', '.') : '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ $detail->jumlah_diterima ? number_format($detail->jumlah_diterima, 0, ',', '.') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($permintaan->distribusi->isNotEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Distribusi terkait</h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($permintaan->distribusi as $d)
                        <li>
                            <a href="{{ route('distribusi.show', $d) }}" class="font-mono text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">
                                {{ $d->nomor_surat_jalan }}
                            </a>
                            <span class="text-xs text-slate-500">· {{ ucfirst(str_replace('_', ' ', $d->status)) }}</span>
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
