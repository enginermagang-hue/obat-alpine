<?php

/**
 * Definisi menu sidebar.
 *
 * Item hanya dirender bila route-nya sudah ada (`Route::has`) DAN user punya
 * permission yang diminta (`permission` opsional). `roles` kosong = semua
 * user login; bila diisi, user harus punya salah satu role tersebut.
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
            ['label' => 'Obat', 'route' => 'obat.index', 'icon' => 'beaker', 'roles' => ['super_admin', 'admin_gudang', 'admin_dinas'], 'permission' => 'view_obat'],
            ['label' => 'Fasilitas Kesehatan', 'route' => 'fasilitas.index', 'icon' => 'building-office-2', 'roles' => ['super_admin', 'admin_dinas'], 'permission' => 'view_fasilitas_kesehatan'],
            ['label' => 'Sumber Dana', 'route' => 'sumber-dana.index', 'icon' => 'banknotes', 'roles' => ['super_admin', 'admin_dinas'], 'permission' => 'view_sumber_dana'],
            ['label' => 'Supplier', 'route' => 'supplier.index', 'icon' => 'truck', 'roles' => ['super_admin', 'admin_gudang'], 'permission' => 'view_suppliers'],
            ['label' => 'Pengguna & Role', 'route' => 'users.index', 'icon' => 'users', 'roles' => ['super_admin', 'admin_dinas'], 'permission' => 'view_users'],
        ],
    ],
    [
        'label' => 'Stok',
        'items' => [
            ['label' => 'Stok Gudang', 'route' => 'stok-gudang.index', 'icon' => 'archive-box', 'roles' => ['super_admin', 'admin_gudang', 'admin_dinas'], 'permission' => 'view_stok_gudang'],
            ['label' => 'Stok Faskes', 'route' => 'stok-faskes.index', 'icon' => 'building-storefront', 'roles' => [], 'permission' => 'view_stok_faskes'],
            ['label' => 'Batch', 'route' => 'batch.index', 'icon' => 'queue-list', 'roles' => [], 'permission' => 'view_batch_stok'],
            ['label' => 'Riwayat Stok', 'route' => 'riwayat-stok.index', 'icon' => 'clock', 'roles' => [], 'permission' => 'view_riwayat_stok'],
            ['label' => 'Stok Opname', 'route' => 'opname.index', 'icon' => 'clipboard-document-check', 'roles' => [], 'permission' => 'view_opname_stok'],
        ],
    ],
    [
        'label' => 'Transaksi',
        'items' => [
            ['label' => 'Penerimaan', 'route' => 'penerimaan.index', 'icon' => 'arrow-down-tray', 'roles' => [], 'permission' => 'view_penerimaan_stok'],
            ['label' => 'Permintaan', 'route' => 'permintaan.index', 'icon' => 'document-text', 'roles' => [], 'permission' => 'view_permintaan_obat'],
            ['label' => 'Distribusi', 'route' => 'distribusi.index', 'icon' => 'paper-airplane', 'roles' => [], 'permission' => 'view_distribusi_obat'],
            ['label' => 'Pemakaian', 'route' => 'pemakaian.index', 'icon' => 'clipboard-document-list', 'roles' => [], 'permission' => 'view_pemakaian_obat'],
            ['label' => 'Retur', 'route' => 'retur.index', 'icon' => 'arrow-uturn-left', 'roles' => [], 'permission' => 'view_retur_obat'],
            ['label' => 'Inspeksi Retur', 'route' => 'inspeksi-retur.index', 'icon' => 'magnifying-glass', 'roles' => [], 'permission' => 'view_inspeksi_retur'],
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
