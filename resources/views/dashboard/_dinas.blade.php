<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    @php
        $cards = [
            ['label' => 'Obat aktif', 'value' => $dinas['kpi']['obat']],
            ['label' => 'Nilai gudang', 'value' => 'Rp '.number_format($dinas['kpi']['nilai_gudang'], 0, ',', '.')],
            ['label' => 'Batch < 90 hari', 'value' => $dinas['kpi']['batch90']],
            ['label' => 'Menunggu persetujuan', 'value' => $dinas['kpi']['pending']],
            ['label' => 'Stok kritis', 'value' => $dinas['kpi']['kritis']],
        ];
    @endphp
    @foreach ($cards as $c)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $c['label'] }}</p>
            <p class="mt-2 text-xl font-semibold">{{ $c['value'] }}</p>
        </div>
    @endforeach
</section>

<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-sm font-semibold">Stok kritis (top 5)</h3>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($dinas['kritisTop'] as $s)
                <li class="flex justify-between gap-2"><span class="truncate">{{ $s->obat?->nama_obat }} · {{ $s->fasilitas?->nama }}</span><span class="font-semibold">{{ number_format($s->jumlah, 0, ',', '.') }}</span></li>
            @empty
                <li class="text-slate-500">Tidak ada stok kritis.</li>
            @endforelse
        </ul>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-sm font-semibold">Tren distribusi 12 bulan</h3>
        <div class="mt-3 h-64"><canvas x-chart="@js($dinas['tren'])"></canvas></div>
    </section>
</div>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <h3 class="text-sm font-semibold">Status puskesmas</h3>
    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2 pr-4">Puskesmas</th><th class="py-2 pr-4 text-right">Kritis</th><th class="py-2 pr-4 text-right">Expired</th><th class="py-2 text-right">Pemakaian bln ini</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($dinas['perFaskes'] as $f)
                    <tr><td class="py-2 pr-4">{{ $f['nama'] }}</td><td class="py-2 pr-4 text-right">{{ $f['kritis'] }}</td><td class="py-2 pr-4 text-right">{{ $f['expired'] }}</td><td class="py-2 text-right">{{ $f['pemakaian'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="flex flex-wrap gap-2">
    <a href="{{ route('permintaan.index') }}" class="btn btn-dark btn-sm">Permintaan</a>
    <a href="{{ route('distribusi.index') }}" class="btn btn-ghost btn-sm">Distribusi</a>
    <a href="{{ route('lplpo.index') }}" class="btn btn-ghost btn-sm">LPLPO</a>
    @if (\Illuminate\Support\Facades\Route::has('prediksi.index'))
        <a href="{{ route('prediksi.index') }}" class="btn btn-ghost btn-sm">Prediksi AI</a>
    @endif
</section>
