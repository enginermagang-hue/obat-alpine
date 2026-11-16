@extends('layouts.app')

@section('title', 'Pengaturan Laporan')
@section('subtitle', 'Kelola akses pengisian RKO per periode')

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Akses RKO Global</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Berlaku untuk semua faskes kecuali yang punya override khusus di bawah.
                Pembuatan RKO oleh faskes hanya bisa dilakukan bila akses dibuka.
            </p>

            <form method="POST" action="{{ route('pengaturan-laporan.global') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="periode_tahun" class="form-label">Periode tahun *</label>
                    <input id="periode_tahun" name="periode_tahun" type="number" min="2000" max="2100" value="{{ old('periode_tahun', $global['periode_tahun'] ?? now()->addYear()->format('Y')) }}" required class="form-input">
                    @error('periode_tahun')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deadline" class="form-label">Deadline pengisian</label>
                    <input id="deadline" name="deadline" type="date" value="{{ old('deadline', $global['deadline'] ?? '') }}" class="form-input">
                </div>

                <div class="flex items-end gap-2 pb-1">
                    <input id="akses_dibuka" name="akses_dibuka" type="checkbox" value="1" @checked(old('akses_dibuka', $global['akses_dibuka']) === '1') class="form-checkbox">
                    <label for="akses_dibuka" class="text-sm font-medium text-slate-700 dark:text-slate-200">Buka akses pengisian RKO</label>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary">Simpan global</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Override per Faskes ({{ $faskesList->count() }})</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Centang “Ikut global” untuk menghapus override dan kembali mengikuti pengaturan global.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Faskes</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Periode</th>
                            <th class="px-5 py-3 font-medium">Deadline</th>
                            <th class="px-5 py-3 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($faskesList as $f)
                            @php
                                $ov = $overrides->get($f->id);
                                $ovAkses = $ov?->firstWhere('key', 'akses_dibuka')?->value;
                                $ovPeriode = $ov?->firstWhere('key', 'periode_tahun')?->value;
                                $ovDeadline = $ov?->firstWhere('key', 'deadline')?->value;
                                $punyaOverride = $ov !== null && $ov->isNotEmpty();
                                $efektifAkses = $ovAkses ?? $global['akses_dibuka'];
                                $efektifPeriode = $ovPeriode ?? $global['periode_tahun'];
                                $efektifDeadline = $ovDeadline ?? $global['deadline'];
                            @endphp
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $f->nama }}</p>
                                    <p class="text-xs capitalize text-slate-500 dark:text-slate-400">{{ $f->tipe }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $efektifAkses === '1' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                        {{ $efektifAkses === '1' ? 'Dibuka' : 'Ditutup' }}{{ $punyaOverride ? ' (khusus)' : '' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $efektifPeriode ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600 dark:text-slate-300">{{ $efektifDeadline ?? '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('pengaturan-laporan.faskes', $f) }}" class="flex flex-wrap items-center justify-end gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="number" name="periode_tahun" min="2000" max="2100" value="{{ $ovPeriode ?? $efektifPeriode ?? now()->addYear()->format('Y') }}" title="Periode tahun khusus" class="form-input w-24">
                                        <input type="date" name="deadline" value="{{ $ovDeadline ?? '' }}" title="Deadline khusus (kosongkan = ikut global)" class="form-input w-auto">
                                        <label class="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" name="akses_dibuka" value="1" @checked(($ovAkses ?? $efektifAkses) === '1') class="form-checkbox">
                                            Buka
                                        </label>
                                        <label class="inline-flex items-center gap-1 text-xs text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" name="ikut_global" value="1" class="form-checkbox">
                                            Ikut global
                                        </label>
                                        <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
