@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Ringkasan stok dan aktivitas terbaru')

@section('content')
    @php
        $hour = (int) now()->format('H');
        $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    @endphp

    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-600 to-accent-700 p-6 text-white shadow-sm sm:p-8">
            <p class="text-sm text-white/80">{{ $greeting }},</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $user->name }}</h2>
            <p class="mt-2 text-sm text-white/80">{{ $fasilitas?->nama ?? 'Gudang Farmasi Dinas Kesehatan' }}</p>
        </section>

        @if ($insight && collect($insight['top'])->isNotEmpty())
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                <p class="text-xs font-semibold uppercase tracking-wide">Insight AI</p>
                <p class="mt-1">{{ $insight['defisit'] }} obat berisiko defisit. Prioritas: <strong>{{ $insight['top'][0]['nama_obat'] ?? '-' }}</strong>.
                    <a href="{{ route('prediksi.index') }}" class="font-medium underline">Lihat Prediksi AI</a>
                </p>
            </section>
        @endif

        @if ($isDinas)
            @include('dashboard._dinas', ['dinas' => $dinas])
        @else
            @include('dashboard._faskes', ['faskes' => $faskes])
        @endif
    </div>
@endsection
