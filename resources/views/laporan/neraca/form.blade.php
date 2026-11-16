@extends('layouts.app')

@section('title', 'Neraca Baru')
@section('subtitle', 'Generate otomatis dari stok saat ini')

@section('content')
    <div class="space-y-6">
        <section>
            <a href="{{ route('neraca.index') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">
                &larr; Kembali
            </a>
        </section>

        @if ($errors->any())
            <section class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">
                <p class="font-medium">Periksa kembali isian berikut:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form method="POST" action="{{ route('neraca.store') }}" class="space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Neraca baru — {{ $faskesNama ?? 'Gudang Dinas' }}</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Detail dihitung otomatis dari stok saat ini (per obat + rincian per sumber dana).</p>
                </div>

                <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                    <div>
                        <label for="tahun" class="form-label">Tahun *</label>
                        <input id="tahun" name="tahun" type="number" min="2000" max="2100" value="{{ old('tahun', now()->year) }}" required class="form-input">
                        @error('tahun')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="catatan" class="form-label">Catatan</label>
                        <input id="catatan" name="catatan" type="text" value="{{ old('catatan') }}" class="form-input">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    <a href="{{ route('neraca.index') }}" class="btn btn-ghost">Batal</a>
                    <button type="submit" class="btn btn-primary">Generate neraca</button>
                </div>
            </section>
        </form>
    </div>
@endsection
