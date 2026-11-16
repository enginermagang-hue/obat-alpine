@extends('layouts.app')

@section('title', 'Riwayat Stok')
@section('subtitle', 'Mutasi stok masuk dan keluar')

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('riwayat-stok.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari keterangan, kode, atau nama obat…" class="form-input">
                </div>

                <select name="tipe" class="form-select">
                    <option value="">Semua tipe</option>
                    @foreach ($tipeList as $tipe)
                        <option value="{{ $tipe }}" @selected(($filters['tipe'] ?? '') === $tipe)>{{ str($tipe)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>

                @unless ($lockedFaskes)
                    <select name="fasilitas" class="form-select">
                        <option value="">Semua faskes + gudang</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}" @selected((string) ($filters['fasilitas'] ?? '') === (string) $f->id)>{{ $f->nama }}</option>
                        @endforeach
                    </select>
                @endunless

                <select name="obat" class="form-select">
                    <option value="">Semua obat</option>
                    @foreach ($obatTerpakai as $o)
                        <option value="{{ $o->id }}" @selected((string) ($filters['obat'] ?? '') === (string) $o->id)>{{ $o->nama_obat }}</option>
                    @endforeach
                </select>

                <div class="grid grid-cols-2 gap-3 lg:col-span-2">
                    <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}" class="form-input">
                    <input type="date" name="sampai" value="{{ $filters['sampai'] ?? '' }}" class="form-input">
                </div>

                <div>
                    <input type="search" name="user" value="{{ $filters['user'] ?? '' }}" placeholder="Petugas…" class="form-input">
                </div>

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a href="{{ route('riwayat-stok.index') }}" class="btn btn-ghost">Atur ulang</a>
                </div>
            </form>

            @if ($riwayat->isEmpty())
                <x-ui.empty-state title="Belum ada riwayat" hint="Riwayat tercatat otomatis dari setiap mutasi stok." icon="clock" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Tanggal</th>
                                <th class="px-5 py-3 font-medium">Obat</th>
                                @unless ($lockedFaskes)
                                    <th class="px-5 py-3 font-medium">Faskes</th>
                                @endunless
                                <th class="px-5 py-3 font-medium">Tipe</th>
                                <th class="px-5 py-3 text-right font-medium">Perubahan</th>
                                <th class="px-5 py-3 text-right font-medium">Sebelum → Sesudah</th>
                                <th class="px-5 py-3 font-medium">Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($riwayat as $row)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40" title="{{ $row->keterangan }}">
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->tanggal?->translatedFormat('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-slate-800 dark:text-slate-100">{{ $row->obat?->nama_obat }}</p>
                                        <p class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $row->obat?->kode_obat }}</p>
                                    </td>
                                    @unless ($lockedFaskes)
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->fasilitas?->nama ?? 'Gudang Dinas' }}</td>
                                    @endunless
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="str_contains($row->tipe, 'masuk') ? 'emerald' : (in_array($row->tipe, ['opname', 'penyesuaian']) ? 'amber' : 'red')">
                                            {{ str($row->tipe)->replace('_', ' ')->title() }}
                                        </x-ui.badge>
                                    </td>
                                    <td @class([
                                        'whitespace-nowrap px-5 py-3 text-right font-semibold',
                                        'text-emerald-600 dark:text-emerald-400' => $row->jumlah > 0,
                                        'text-red-600 dark:text-red-400' => $row->jumlah <= 0,
                                    ])>
                                        {{ $row->jumlah > 0 ? '+' : '' }}{{ number_format($row->jumlah, 0, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-xs text-slate-500 dark:text-slate-400">
                                        {{ number_format($row->stok_sebelum, 0, ',', '.') }} &rarr; {{ number_format($row->stok_sesudah, 0, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->user?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.pagination :paginator="$riwayat" />
            @endif
        </section>
    </div>
@endsection
