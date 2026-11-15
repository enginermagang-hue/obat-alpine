<?php

/**
 * Definisi menu sidebar.
 *
 * Item hanya dirender bila route-nya sudah ada (`Route::has`), sehingga menu
 * tumbuh otomatis seiring modul ditambahkan. `roles` kosong = semua user login.
 */
return [
    [
        'label' => null,
        'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'roles' => []],
        ],
    ],
    [
        'label' => 'Master Data',
        'items' => [
            ['label' => 'Obat', 'route' => 'obat.index', 'icon' => 'beaker', 'roles' => ['super_admin', 'admin_gudang', 'admin_dinas']],
            ['label' => 'Fasilitas Kesehatan', 'route' => 'fasilitas.index', 'icon' => 'building-office-2', 'roles' => ['super_admin', 'admin_dinas']],
            ['label' => 'Sumber Dana', 'route' => 'sumber-dana.index', 'icon' => 'banknotes', 'roles' => ['super_admin', 'admin_dinas']],
            ['label' => 'Supplier', 'route' => 'supplier.index', 'icon' => 'truck', 'roles' => ['super_admin', 'admin_gudang']],
            ['label' => 'Pengguna & Role', 'route' => 'users.index', 'icon' => 'users', 'roles' => ['super_admin', 'admin_dinas']],
        ],
    ],
    [
        'label' => 'Stok',
        'items' => [
            ['label' => 'Stok Gudang', 'route' => 'stok-gudang.index', 'icon' => 'archive-box', 'roles' => ['super_admin', 'admin_gudang', 'admin_dinas']],
            ['label' => 'Stok Faskes', 'route' => 'stok-faskes.index', 'icon' => 'building-storefront', 'roles' => []],
            ['label' => 'Batch', 'route' => 'batch.index', 'icon' => 'queue-list', 'roles' => []],
            ['label' => 'Riwayat Stok', 'route' => 'riwayat-stok.index', 'icon' => 'clock', 'roles' => []],
            ['label' => 'Stok Opname', 'route' => 'opname.index', 'icon' => 'clipboard-document-check', 'roles' => []],
        ],
    ],
    [
        'label' => 'Transaksi',
        'items' => [
            ['label' => 'Penerimaan', 'route' => 'penerimaan.index', 'icon' => 'arrow-down-tray', 'roles' => ['super_admin', 'admin_gudang']],
            ['label' => 'Permintaan', 'route' => 'permintaan.index', 'icon' => 'document-text', 'roles' => []],
            ['label' => 'Distribusi', 'route' => 'distribusi.index', 'icon' => 'paper-airplane', 'roles' => ['super_admin', 'admin_gudang']],
            ['label' => 'Pemakaian', 'route' => 'pemakaian.index', 'icon' => 'clipboard-document-list', 'roles' => []],
            ['label' => 'Retur', 'route' => 'retur.index', 'icon' => 'arrow-uturn-left', 'roles' => []],
        ],
    ],
    [
        'label' => 'Laporan',
        'items' => [
            ['label' => 'LPLPO', 'route' => 'lplpo.index', 'icon' => 'document-chart-bar', 'roles' => []],
            ['label' => 'RKO', 'route' => 'rko.index', 'icon' => 'chart-bar', 'roles' => []],
            ['label' => 'Neraca Tahunan', 'route' => 'neraca.index', 'icon' => 'scale', 'roles' => []],
            ['label' => 'Tutup Buku', 'route' => 'tutup-buku.index', 'icon' => 'lock-closed', 'roles' => ['super_admin', 'admin_dinas']],
            ['label' => 'Alokasi Dana', 'route' => 'alokasi-dana.index', 'icon' => 'calculator', 'roles' => ['super_admin', 'admin_dinas']],
        ],
    ],
    [
        'label' => 'Prediksi',
        'items' => [
            ['label' => 'Prediksi AI', 'route' => 'prediksi.index', 'icon' => 'sparkles', 'roles' => []],
        ],
    ],
    [
        'label' => 'Pengaturan',
        'items' => [
            ['label' => 'Profil & Preferensi', 'route' => 'profil.edit', 'icon' => 'user-circle', 'roles' => []],
            ['label' => 'Panduan', 'route' => 'panduan.index', 'icon' => 'book-open', 'roles' => []],
        ],
    ],
];
