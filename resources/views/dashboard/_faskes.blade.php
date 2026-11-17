<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @php
        $cards = [
            ['label' => 'Stok kritis', 'value' => $faskes['kpi']['kritis']],
            ['label' => 'Stok aman', 'value' => $faskes['kpi']['aman']],
            ['label' => 'Pemakaian bulan ini', 'value' => $faskes['kpi']['pemakaian']],
            ['label' => 'Permintaan berjalan', 'value' => $faskes['kpi']['berjalan']],
        ];
    @endphp
    @foreach ($cards as $c)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $c['label'] }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ $c['value'] }}</p>
        </div>
    @endforeach
</section>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-sm font-semibold">Prediksi kebutuhan (top 3)</h3>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($faskes['prediksi']['top'] as $r)
                <li class="flex justify-between gap-2"><span class="truncate">{{ $r['nama_obat'] }}</span><span class="font-semibold">{{ number_format($r['rekom'], 0, ',', '.') }}</span></li>
            @empty
                <li class="text-slate-500">Belum ada prediksi.</li>
            @endforelse
        </ul>
        <a href="{{ route('prediksi.index') }}" class="mt-3 inline-block text-xs font-medium text-brand-600 underline">Lihat Prediksi AI (akurasi {{ number_format(($faskes['prediksi']['akurasi'] ?? 0) * 100, 1) }}%)</a>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-sm font-semibold">Batch akan kedaluwarsa</h3>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($faskes['batchExp'] as $b)
                <li class="flex justify-between gap-2"><span class="truncate">{{ $b->obat?->nama_obat }}</span><span>{{ $b->tanggal_expired?->format('d/m/Y') }}</span></li>
            @empty
                <li class="text-slate-500">Tidak ada batch mendekati kedaluwarsa.</li>
            @endforelse
        </ul>
    </section>
</div>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-sm font-semibold">Pemakaian terbaru</h3>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($faskes['pemakaianTerakhir'] as $p)
                <li class="flex justify-between gap-2"><span class="truncate">{{ $p->nomor_pemakaian ?? 'Pemakaian #'.$p->id }}</span><span class="text-slate-500">{{ $p->tanggal_pemakaian?->format('d/m/Y') }}</span></li>
            @empty
                <li class="text-slate-500">Belum ada pemakaian.</li>
            @endforelse
        </ul>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-sm font-semibold">Permintaan berjalan</h3>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($faskes['permintaanBerjalan'] as $p)
                <li class="flex justify-between gap-2"><span class="truncate">{{ $p->nomor_permintaan ?? 'Permintaan #'.$p->id }}</span><span class="text-slate-500">{{ $p->status }}</span></li>
            @empty
                <li class="text-slate-500">Tidak ada permintaan berjalan.</li>
            @endforelse
        </ul>
    </section>
</div>
