@props(['status'])

@php
    $map = [
        // Penerimaan
        'dikonfirmasi' => ['tone' => 'emerald', 'label' => 'Dikonfirmasi'],
        'dibatalkan' => ['tone' => 'slate', 'label' => 'Dibatalkan'],
        // Permintaan
        'menunggu_persetujuan' => ['tone' => 'amber', 'label' => 'Menunggu persetujuan'],
        'disetujui' => ['tone' => 'brand', 'label' => 'Disetujui'],
        'ditolak' => ['tone' => 'red', 'label' => 'Ditolak'],
        'sedang_didistribusi' => ['tone' => 'violet', 'label' => 'Sedang didistribusi'],
        'diterima' => ['tone' => 'emerald', 'label' => 'Diterima'],
        // Retur
        'menunggu_approval' => ['tone' => 'amber', 'label' => 'Menunggu approval'],
        'dalam_pengiriman' => ['tone' => 'amber', 'label' => 'Dalam pengiriman'],
        // Umum
        'draft' => ['tone' => 'slate', 'label' => 'Draft'],
        'proses' => ['tone' => 'brand', 'label' => 'Proses'],
        'selesai' => ['tone' => 'emerald', 'label' => 'Selesai'],
    ];

    $tones = [
        'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'brand' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
        'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
        'red' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    ];

    $entry = $map[$status] ?? ['tone' => 'slate', 'label' => ucfirst(str_replace('_', ' ', $status))];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium '.($tones[$entry['tone']] ?? $tones['slate'])]) }}>
    {{ $entry['label'] }}
</span>
