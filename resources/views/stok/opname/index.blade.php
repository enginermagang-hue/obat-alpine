@extends('layouts.app')

@section('title', 'Stok Opname')
@section('subtitle', 'Perhitungan stok fisik berkala')

@section('content')
    <div class="space-y-6">
        <section class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @can('create', App\Models\OpnameStok::class)
                    <a href="{{ route('opname.create') }}" class="btn btn-primary">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        Opname Baru
                    </a>
                @endcan
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('opname.index') }}" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-6">
                <div>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nomor…" class="form-input">
                </div>

                <select name="tipe" class="form-select">
                    <option value="">Semua tipe</option>
                    <option value="penyesuaian" @selected(($filters['tipe'] ?? '') === 'penyesuaian')>Penyesuaian</option>
                    <option value="stok_awal" @selected(($filters['tipe'] ?? '') === 'stok_awal')>Stok awal</option>
                    <option value="stok_baru" @selected(($filters['tipe'] ?? '') === 'stok_baru')>Stok baru</option>
                </select>

                <select name="status" data-tomselect-search="false" class="form-select">
                    <option value="">Semua status</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                    <option value="proses" @selected(($filters['status'] ?? '') === 'proses')>Proses</option>
                    <option value="selesai" @selected(($filters['status'] ?? '') === 'selesai')>Selesai</option>
                </select>

                @unless ($lockedFaskes)
                    <select name="fasilitas" class="form-select">
                        <option value="">Semua lokasi</option>
                        @foreach ($faskesList as $f)
                            <option value="{{ $f->id }}" @selected((string) ($filters['fasilitas'] ?? '') === (string) $f->id)>{{ $f->nama }}</option>
                        @endforeach
                    </select>
                @endunless

                <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}" class="form-input">
                <input type="date" name="sampai" value="{{ $filters['sampai'] ?? '' }}" class="form-input">

                <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn btn-dark">Terapkan</button>
                    <a href="{{ route('opname.index') }}" class="btn btn-ghost">Atur ulang</a>
                </div>
            </form>

            @if ($opnames->isEmpty())
                <x-ui.empty-state title="Belum ada opname" hint="Buat opname baru untuk mencocokkan stok fisik." icon="clipboard-document-check" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 font-medium">Nomor</th>
                                <th class="px-5 py-3 font-medium">Tipe</th>
                                @unless ($lockedFaskes)
                                    <th class="px-5 py-3 font-medium">Lokasi</th>
                                @endunless
                                <th class="px-5 py-3 font-medium">Tanggal</th>
                                <th class="px-5 py-3 text-right font-medium">Item</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($opnames as $row)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <a href="{{ route('opname.show', $row) }}" class="font-mono text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">
                                            {{ $row->nomor_opname }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$row->tipe === 'penyesuaian' ? 'amber' : ($row->tipe === 'stok_awal' ? 'emerald' : 'brand')">
                                            {{ $row->tipe === 'penyesuaian' ? 'Penyesuaian' : ($row->tipe === 'stok_awal' ? 'Stok awal' : 'Stok baru') }}
                                        </x-ui.badge>
                                    </td>
                                    @unless ($lockedFaskes)
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->fasilitas?->nama ?? 'Gudang Dinas' }}</td>
                                    @endunless
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->tanggal_opname?->translatedFormat('d M Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right text-slate-600 dark:text-slate-300">{{ $row->details_count }}</td>
                                    <td class="px-5 py-3">
                                        <x-ui.badge :tone="$row->status === 'selesai' ? 'emerald' : ($row->status === 'proses' ? 'brand' : 'slate')">
                                            {{ ucfirst($row->status) }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $row->user?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.pagination :paginator="$opnames" />
            @endif
        </section>
    </div>
@endsection
