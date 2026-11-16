# Ruang Obat — Laravel + Blade + Alpine

Aplikasi manajemen stok obat Dinas Kesehatan (alur **Gudang Dinas → Puskesmas → Pustu**),
dibangun ulang dari project Filament `D:\project\obat` menjadi **Laravel + Blade + Alpine.js**
tanpa Filament/Livewire.

Rencana kerja lengkap & status per fase: [`plan.md`](plan.md).

---

## Stack

| Komponen | Versi |
| --- | --- |
| Laravel | 13.42 |
| PHP | 8.4 |
| Alpine.js | 3.17 |
| Tailwind CSS | 4.3 (Vite 7.3) |
| Database dev | SQLite (`database/database.sqlite`) + WAL |
| Database uji | SQLite `:memory:` |
| Database produksi | MySQL 8 (skema referensi: DB `obat`) |
| Auth | session + spatie/laravel-permission, Google OAuth opsional |
| PDF / Excel | spatie/laravel-pdf (Browsershot) + dompdf, PhpSpreadsheet |

---

## Status

| Fase | Isi | Status |
| --- | --- | --- |
| 0a | 45 migration → 54 tabel, SQLite dev, verifier skema | ✅ |
| 0b | `.env` SQLite, `phpunit.xml` `:memory:`, `git init` | ✅ |
| 0c | spatie permission/activitylog, seeder master + akun | ✅ |
| 1a | layout (sidebar/topbar, dark mode), auth, dashboard, profil | ✅ |
| 1b | 24 service, 24 policy, 21 command, 10 controller cetak, data dummy + model AI | ✅ |
| 2 | Master data (5 modul: Obat, Faskes, Sumber Dana, Supplier, User & Role) | ✅ |
| 3 | Stok & batch (Stok Gudang/Faskes, Batch, Riwayat, Opname; tabel Alpine server-backed) | ✅ |
| 3–8 | Stok, transaksi, laporan, prediksi, backlog | ⏳ |

Data dummy saat ini: 12 bulan (Des 2025 – Nov 2026), 18.926 pemakaian, 4.419 batch,
72 distribusi, 72 permintaan, 72 LPLPO, 6 RKO, dan 318 model AI terlatih.

---

## Setup di device baru

Prasyarat: **PHP 8.4+**, **Composer 2**, **Node 20+**, **Git**. (Laragon/Windows OK;
Linux/macOS pakai perintah setara.)

```powershell
git clone https://github.com/enginermagang-hue/obat-alpine.git
cd obat-alpine

composer install
npm install

# .env
Copy-Item .env.example .env
php artisan key:generate

# DB dev SQLite (wajib dibuat manual, file-nya di-gitignore)
New-Item -ItemType File database\database.sqlite -Force

# skema + data master + akun
php artisan migrate --seed

# opsional: data transaksi 12 bulan + latih model AI
php artisan dummy:generate --months=12 --end-date=2026-11 --force
php artisan ai:train-models

# aset frontend
npm run build          # atau: npm run dev (HMR)

php artisan serve
```

Buka `http://127.0.0.1:8000`.

> DB dev **tidak** ikut repo (`.gitignore`: `database/*.sqlite*`). Semua data dibangun ulang
> dari migration + seeder + `dummy:generate`, jadi tidak perlu copy file database.

### Akun uji (dari `UserSeeder`, password semua `user123`)

| Email | Role |
| --- | --- |
| `instalasifarmasi.kabkupang@gmail.com` | `super_admin` |
| `gudangfarmasi.kabkupang@gmail.com` | `admin_gudang` |
| `adminfarmasi.kabkupang@gmail.com` | `admin_dinas` |

9 akun total; user puskesmas/pustu terhubung ke fasilitasnya masing-masing.

---

## Perintah harian

```powershell
php artisan test                  # 78 test (SQLite :memory:) — tabel diuji via kontrak JSON
vendor\bin\pint                  # format kode
php artisan migrate:fresh --seed  # reset DB dev (aman, tanpa data produksi)

# verifikasi skema migration vs DB referensi MySQL `obat`
php database/schema-diff.php                 # target SQLite dev
php database/schema-diff.php --target=mysql  # target MySQL obat_v2 (opsional)

# data dummy & AI
php artisan dummy:generate --months=12 --end-date=2026-11 --force
php artisan ai:train-models
php artisan schedule:list
```

---

## Struktur penting

```
app/
  Http/Controllers/
    Auth/LoginController.php        # login, logout, throttle
    Auth/GoogleController.php       # OAuth Google (opsional)
    DashboardController.php         # KPI + batch mendekati kedaluwarsa
    ProfilController.php            # preferensi, password, unlink Google
    Master/ObatController.php       # CRUD + nonaktifkan + filter query string
    Master/FasilitasKesehatanController.php # CRUD + validasi induk puskesmas
    Master/SumberDanaController.php   # CRUD + unique komposit kode+tahun
    Master/SupplierController.php     # CRUD + guard penerimaan
    Master/UserController.php         # CRUD + reset password + syncRoles
    Master/RoleController.php         # CRUD role + matriks permission
    Stok/StokGudangController.php     # KPI, filter, drill-down batch, hitung minimum
    Stok/StokFaskesController.php     # scope faskes, filter expiry
    Stok/BatchController.php          # filter faskes/status/expiry, KPI nilai
    Stok/RiwayatStokController.php    # filter tipe/tanggal/obat/faskes
    Stok/OpnameController.php         # form multi-baris, 3 tahap status, reopen
    Api/LookupController.php          # /api/obat/search, /api/batch, /api/stok-sistem
    Master/ImportObatController.php # upload CSV + template (job async)
    CetakObatExcelController.php    # export Excel master obat (filter dari index)
    Cetak*.php                      # 10 controller PDF/Excel (reuse project lama)
  Http/Middleware/SetLocaleFromPreferences.php
  Jobs/ImportObatJob.php          # import CSV obat (upsert by kode_obat)
  Policies/                         # 25 policy (BatchStokPolicy baru Fase 3)
  Support/FaskesAccess.php          # visibleFasilitasIds + scopeQuery (dipakai semua list stok)
  Services/StokService.php          # fix: reverseOpname pulihkan batch utk selisih negatif
  Models/                           # 37 model + Traits/LogsActivity
  Notifications/AppNotification.php # notifikasi database (pengganti Filament)
  Console/Commands/                 # 21 command (AI, tutup buku, cleanup, docs)
config/navigation.php               # menu sidebar/topbar (filter route + role + permission)
database/factories/                 # User, Obat, Faskes, SumberDana, Supplier, BatchStok, RiwayatStok
database/schema-diff.php            # verifier paritas skema (SQlite & MySQL)
resources/js/components/modal.js    # Alpine.data('modal') reuse semua modul
resources/js/components/docForm.js  # Alpine.data('opnameForm') form multi-baris
resources/views/
  layouts/app.blade.php             # shell aplikasi
  components/ui/                    # modal, badge, empty-state, pagination
  master/obat/index.blade.php       # list + filter + modal create/edit + import
  master/fasilitas/index.blade.php  # + validasi induk puskesmas (TomSelect)
  master/sumber-dana/index.blade.php
  master/supplier/index.blade.php
  master/users/index.blade.php      # tab Pengguna + reset password
  master/roles/index.blade.php      # tab Role + matriks permission
  auth/login.blade.php
  dashboard.blade.php
  profil/edit.blade.php
  pdf/                              # 11 template cetak
routes/web.php                      # auth + profil + master + stok + api lookup
routes/console.php                  # scheduler
```

---

## Catatan teknis

- **SQLite dev**: WAL + `busy_timeout=5000` diatur di `config/database.php`
  (`DB_JOURNAL_MODE`, `DB_BUSY_TIMEOUT`, `DB_SYNCHRONOUS`). File `-wal`/`-shm` di-gitignore.
- **Perbedaan SQLite vs MySQL**: `decimal` disimpan sebagai floating point (jangan assert uang persis),
  `useCurrentOnUpdate` diabaikan, `lockForUpdate` no-op. Uji akhir tetap di MySQL.
- **SQL portabel**: raw SQL MySQL-only (`DATE_FORMAT`, `GROUP_CONCAT`, `SET FOREIGN_KEY_CHECKS`)
  harus punya cabang `DB::connection()->getDriverName() === 'sqlite'`.
- **Skema**: hanya lewat migration. DB `obat` (MySQL) adalah **referensi read-only**, bukan sumber data.
- **PDF**: Browsershot butuh Node + Chrome/Chromium di mesin. `dompdf` tersedia sebagai fallback.
- **Google OAuth**: isi `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`; kalau kosong tombol link otomatis nonaktif.
- **Logo/asset**: `public/assets/images/avatars` (22 preset) ikut repo agar `AvatarPresetSeeder` berjalan.

### Troubleshooting

| Gejala | Solusi |
| --- | --- |
| `SQLiteDatabaseDoesNotExistException` | `New-Item database\database.sqlite -Force` |
| `database is locked` | pastikan WAL aktif (`DB_JOURNAL_MODE=WAL`), hindari dua proses tulis berat |
| `Vite manifest not found` | `npm run build` (atau jalankan `npm run dev`) |
| `no such table` | `php artisan migrate` |
| Test lambat/gagal DB | `phpunit.xml` memaksa `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| Login gagal setelah `migrate:fresh` | jalankan `--seed` (akun berasal dari `UserSeeder`) |

---

## Lanjutan (TODO berikutnya)

1. **Fase 2 — Master data (5 modul ✅)**: Obat, Fasilitas, Sumber Dana, Supplier, User & Role —
   pola `Controller + Request + modal Alpine + x-ui/*` dipakai ulang Fase 3.
   Butuh `php artisan queue:work` agar import CSV obat async jalan di dev.
2. **Fase 3 — Stok & batch (✅)**: Stok Gudang/Faskes (KPI, status Habis/Menipis/Tersedia,
   drill-down batch, hitung stok minimum), Batch (KPI nilai & kadaluarsa),
   Riwayat Stok (filter tipe/tanggal), Opname (form multi-baris, 3 tahap status,
   kunci + buka kembali, endpoint lookup obat/batch).
   Perbaikan: `reverseOpname` kini memulihkan batch untuk selisih negatif;
   puskesmas/pustu diberi `view_batch_stok`; nav sadar-permission.
3. **Fase 3.5 — Tabel Alpine (✅)**: 10 tabel index (5 master + 5 stok) memakai
   `Alpine.data('dataTable')` server-backed — filter/sort/pagination tanpa reload,
   payload per halaman ±10 KB, scope faskes tetap di SQL, URL tersinkron
   (bookmark & back jalan), link Export Excel ikut filter aktif.
   Test tabel diubah ke kontrak JSON (`GET ?x=y` → `{data, meta}`); test show/detail tetap SSR.
3. Route cetak (10 controller) belum dipasang — dipasang bersama modul transaksi/laporan (Fase 4–6).
4. `PanduanController` + view panduan belum dibawa (butuh konten Markdown dari project lama).
5. Notifikasi database (`AppNotification`) sudah tersimpan, tapi belum ada halaman inbox/bell.
6. Tambah test fitur per modul; pertimbangkan konversi ke Pest (§5.1 `plan.md`).
7. i18n: pesan validasi masih Inggris (belum ada file `lang/id`); tanggal sudah Indonesia via Carbon.

Detail keputusan & risiko ada di [`plan.md`](plan.md) §13 dan §15.

