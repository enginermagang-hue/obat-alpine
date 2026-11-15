# Plan — Rewrite "Ruang Obat" dengan Laravel + Blade + Alpine.js

> Dokumen ini adalah rencana kerja project `D:\project\obat-alpine`.
> Status terakhir diperbarui: 15 November 2026.
> Revisi 15 Nov 2026: inventaris kode & temuan skema diverifikasi langsung ke `D:\project\obat` dan DB `obat`.

---

## 1. Tujuan

Membangun ulang aplikasi manajemen stok obat Dinas Kesehatan (alur **Gudang Dinas → Puskesmas → Pustu**) dengan:

- **Laravel 13** sebagai backend.
- **Blade + Alpine.js 3** sebagai frontend — **tanpa Filament, tanpa Livewire**.
- **Tailwind CSS 4 + Vite 7** untuk styling & bundling.
- Logika bisnis (stok per batch, FEFO/FIFO/LIFO, prediksi AI, pelaporan) **dipertahankan** dari project lama `D:\project\obat`, bukan ditulis ulang.
- Database: skema **dibangun ulang lewat migration file baru** (54 tabel), **bukan clone**.
  Dev pakai **SQLite** (`database/database.sqlite`, WAL); produksi MySQL 8 sesuai referensi.
  DB lama `obat` hanya dipakai sebagai **referensi baca** (tidak disalin, tidak diubah).

Referensi skema: `D:\project\obat\docs\Struktur Database.md` — ini **sumber tunggal**; hapus salinan
`Struktur Database.md` di root project baru agar tidak ada dua versi yang bisa berbeda.

---

## 2. Status saat ini

| Komponen | Versi | Status |
| --- | --- | --- |
| Laravel | 13.42.0 | ✅ terpasang |
| PHP | 8.4.12 | ✅ |
| Alpine.js | 3.17.5 | ✅ ter-wire di `resources/js/app.js` |
| Tailwind CSS | 4.3.5 | ✅ bawaan skeleton |
| Vite | 7.3.8 | ✅ `npm run build` sukses |
| Git | — | ❌ belum `git init` |
| Paket Composer pendukung | — | ❌ belum ada (lihat §5.1) |
| Skema migration | — | ✅ 43 file (skeleton disesuaikan + 38 domain + FK + notifications) |
| Database dev | SQLite + WAL | ✅ 48 tabel, `migrate` sukses, selisih skema 0 |
| Database uji | SQLite `:memory:` | ✅ `phpunit.xml` force sqlite (2 test hijau) |
| Seeder master + user | — | ❌ belum dipasang |
| Filament | — | ✅ tidak terpasang |

File yang sudah diubah:

```js
// resources/js/app.js
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
```

Belum dikerjakan: publish migration package (spatie), seeder, `git init`, auth, layout, seluruh modul.

### 2.1 Referensi skema (dibaca dari DB `obat`, read-only)

Angka di bawah adalah target yang harus dipenuhi migration baru — bukan data yang akan di-clone.

| Item | Nilai | Catatan |
| --- | --- | --- |
| Tabel | 54 | 55 di referensi; `settings` (legacy, tidak dipakai) tidak dibuat |
| Kolom | 560 | harus cocok nama/tipe/nullable/default |
| Foreign key | 99 | harus cocok `on delete`/`on update` |
| Kolom `enum` | 35 | nilai enum harus identik (dipakai Model/Service lama) |
| JSON / longtext | 4 / 6 | |
| Trigger / routine / event | 0 / 0 / 0 | tidak perlu SQL manual |
| View `stock_summary_view` | rusak | join ke `stock`/`items`/`item_categories` yang tidak ada → **tidak dibuat** |
| Ukuran data lama | 125,6 MB | tidak ikut; DB baru mulai kosong |
| Users | 18 akun | tidak dipakai; akun dibuat seeder |
| Migrations lama | 96 file | tidak dipakai; hanya referensi penulisan |
| Models | 37 model + 1 trait (`Traits/LogsActivity.php`) | hanya `User` yang implement Filament (`HasAvatar`) |
| Services | 24 | 20 di root + `Ann/` (3) + `Laporan/` (1) |
| Policies | 24 | 2 import `App\Filament\Pages\PrediksiAiPage` |
| Console Commands | 21 | termasuk 6 command Tutup Buku + `AiTrainModels` |
| Controllers | 15 | 10 `Cetak*` + auth/panduan/download; semua flat, tanpa subfolder |
| PDF views | 8 | `resources/views/pdf/*` |
| Filament | 23 Resource + Pages (182 file) | acuan checklist paritas §14 |
| Livewire | 1 file | tidak dipakai |
| Enums / Helpers | 1 / 1 | `MetodeStok`, `BatchNumberGenerator` |
| Support | 1 file | `ModalRepeaterColumn.php` — murni Filament, **tidak dibawa** |

---

## 3. Keputusan arsitektur (default — ubah jika perlu)

| Aspek | Keputusan |
| --- | --- |
| Folder project | `D:\project\obat-alpine` |
| Database dev | SQLite `database/database.sqlite` + WAL | target utama pengembangan harian |
| Database uji | SQLite `:memory:` | `phpunit.xml` force sqlite (sama seperti project lama) |
| Database produksi | MySQL 8 (skema referensi `obat`) | migration harus portabel ke keduanya |
| Sumber skema | 38 migration domain baru + tabel sistem (Laravel/spatie) — lihat §4 |
| Verifikasi skema | `database/schema-diff.php` mode sqlite & mysql | 0 selisih sebelum copy Models/Services |
| Versi Laravel | 13 (sama dengan project lama → reuse paling mulus) |
| Auth | Pakai ulang `CustomAuthController` + `GoogleSocialiteController`, role via `spatie/laravel-permission` |
| UI | Blade layout + Alpine component; data via controller + JSON endpoint tipis |
| Chart | Chart.js via Alpine directive |
| Cetak | Pakai ulang controller PDF/Excel project lama |
| Import/Export | Ditulis sendiri (Filament Actions tidak tersedia) |
| Tidak dipakai | `app/Filament`, `app/Livewire`, semua paket `filament/*` |

### Struktur folder target

```
app/
  Enums/                 # MetodeStok, + enum status baru
  Helpers/               # BatchNumberGenerator
  Http/
    Controllers/
      Auth/              # login, logout, google
      Master/            # obat, faskes, sumber dana, supplier, user
      Stok/              # stok gudang, stok faskes, batch, opname, riwayat
      Transaksi/         # penerimaan, distribusi, permintaan, retur, pemakaian
      Laporan/           # lplpo, rko, neraca, tutup buku, alokasi dana
      Prediksi/          # model, prediksi, rekomendasi
      Api/               # endpoint JSON: search obat, batch, stok, notifikasi
      Cetak/             # PDF & Excel (reuse)
    Middleware/
    Requests/            # FormRequest per dokumen
  Models/                # reuse 37 model + Models/Traits/LogsActivity
  Policies/              # reuse 24 policy; 2 file lepas import Filament Pages
  Services/              # reuse 24 service (20 root + Ann/ + Laporan/)
resources/
  css/app.css
  js/
    app.js               # bootstrap Alpine + stores
    components/          # docForm.js, tableFilter.js, toast.js, chart.js
  views/
    layouts/app.blade.php
    components/ui/       # x-ui.modal, x-ui.table, x-ui.badge, x-ui.pagination
    components/form/     # x-form.obat-picker, x-form.batch-picker, x-form.rows
    master/ stok/ transaksi/ laporan/ prediksi/
    pdf/                 # reuse 8 template lama
routes/
  web.php
  console.php
```

---

## 4. Fase 0 — Skema via migration (tanpa clone data)

**Prinsip:** tidak ada data yang disalin. `obat` tetap utuh dan hanya dibaca; `obat_v2` dibuat **kosong**
lalu dibangun ulang dari `database/migrations`.

### 4.1 Pembagian 55 tabel

| Kelompok | Jumlah | Sumber migration |
| --- | --- | --- |
| Laravel core | 9 | skeleton: `users` (+`fasilitas_kesehatan_id`, `google_login_enabled`, `last_active_at`), `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications` |
| Spatie permission | 5 | publish `spatie/laravel-permission` |
| Spatie activitylog | 1 | publish `spatie/laravel-activitylog` (`activity_log`) |
| `settings` | 1 | **tidak dibuat** — tabel package `spatie/laravel-settings`, tidak ada referensi di `app/` maupun `config/` project lama |
| `migrations` | 1 | otomatis oleh `php artisan migrate` |
| **Domain obat** | **38** | **migration file baru** |
| **Total** | **54** | 55 di referensi − `settings` |

38 tabel domain:

| Modul | Tabel |
| --- | --- |
| Master (8) | `fasilitas_kesehatan`, `obat`, `sumber_dana`, `suppliers`, `avatar_presets`, `user_preferences`, `pengaturan_laporan`, `socialite_users` |
| Stok (6) | `stok_gudang`, `stok_faskes`, `batch_stok`, `riwayat_stok`, `opname_stok`, `detail_opname_stok` |
| Transaksi (11) | `penerimaan_stok`, `detail_penerimaan_stok`, `permintaan_obat`, `detail_permintaan_obat`, `distribusi_obat`, `detail_distribusi_obat`, `retur_obat`, `detail_retur_obat`, `inspeksi_retur`, `pemakaian_obat`, `detail_pemakaian_obat` |
| Laporan (9) | `laporan_lplpo`, `detail_lplpo`, `laporan_rko`, `detail_rko`, `neraca_tahunan`, `detail_neraca_tahunan`, `detail_neraca_sumber_dana`, `tutup_buku`, `sumber_dana_penggunaan` |
| Prediksi (2) | `model_prediksi`, `prediksi_kebutuhan` |
| Import/Export (2) | `imports`, `exports` |

Yang **tidak** dibuat: view `stock_summary_view`, index duplikat `idx_pemakaian_header_v2`, kolom
`laporan_lplpo.jenis_pengajuan`, tabel `settings`, dan 4 tabel yatim project lama (`failed_import_rows`,
`import_data_historis`, `pengaturan_laporan_new`, `pengaturan_laporan_old`).

**Status implementasi (16 Nov 2026):** 43 file migration (3 skeleton disesuaikan + 38 create domain + 1 FK +
1 notifications) sudah ada. Di **SQLite dev** hasilnya **48 tabel** dan di MySQL `obat_v2` juga **48 tabel**;
selisih kolom/index/FK **0 di kedua mode** (`php database/schema-diff.php [--target=mysql]`). Sisa 6 tabel
(`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions`, `activity_log`)
menunggu package dipasang + `vendor:publish` di Fase 1.

### 4.2 Langkah

```powershell
# 1. DB dev SQLite (WAL + busy_timeout diatur di config/database.php)
New-Item -ItemType File database\database.sqlite -Force

# 2. Init repo git
git init
git add . && git commit -m "chore: skeleton Laravel 13 + Alpine + Tailwind"

# 3. Jalankan migration (38 domain + 10 sistem)
php artisan migrate

# 4. Seeder
php artisan db:seed

# 5. Verifikasi paritas skema (§4.4)
php database/schema-diff.php                 # target SQLite (default)
php database/schema-diff.php --target=mysql  # opsional, target obat_v2
```

`.env`:

```dotenv
APP_NAME="Ruang Obat"
DB_CONNECTION=sqlite
# DB_DATABASE=            # kosong = database/database.sqlite
DB_FOREIGN_KEYS=true
DB_BUSY_TIMEOUT=5000
DB_JOURNAL_MODE=WAL
DB_SYNCHRONOUS=NORMAL

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Koneksi SQLite memakai fitur bawaan Laravel 13 (`busy_timeout`, `journal_mode`, `synchronous` di
`config/database.php`), jadi WAL aktif tanpa service provider. Hasil cek koneksi: `journal_mode=wal`,
`busy_timeout=5000`, `foreign_keys=1`, dan insert dengan FK tidak valid ditolak (SQLSTATE 23000).

> DB dev boleh di-reset kapan saja: `php artisan migrate:fresh --seed`. Tidak ada data produksi di sini.
> MySQL `obat` tetap read-only; `obat_v2` hanya dipakai bila ingin verifikasi mode MySQL.

### 4.3 Cara menulis migration

Dua jalur; **yang dipakai: B**, hasilnya di-review lalu dirapikan manual.

| Jalur | Cara | Status |
| --- | --- | --- |
| A. Tulis manual | 1 file per tabel dari `Struktur Database.md` | tidak dipakai (560 kolom terlalu rawan) |
| **B. Generate lalu rapikan** | generator sekali pakai membaca `information_schema` dari `obat` (read-only), lalu hasil di-review | **dipakai** — script bersifat sekali jalan, tidak disimpan di repo |

Aturan penulisan yang sudah diterapkan:

1. Semua tabel dibuat lebih dulu, **semua foreign key (95) ditambahkan di satu migration terakhir**
   (`2026_01_01_000900_add_foreign_keys.php`) → urutan create tidak jadi masalah, siklus FK aman.
2. Nilai `enum` disalin **persis** dari `obat` (35 kolom) karena dipakai Model/Service lama.
3. `decimal` ikut precision/scale asli; `timestamp` vs `datetime` tidak disamakan; `useCurrent()` /
   `useCurrentOnUpdate()` mengikuti `DEFAULT_GENERATED` dan `ON UPDATE CURRENT_TIMESTAMP`.
   Catatan: SQLite mengabaikan `ON UPDATE CURRENT_TIMESTAMP` (lihat §15).
4. Index komposit dipertahankan termasuk nama aslinya (`idx_pemakaian_faskes_obat`,
   `pemakaian_obat_nomor_unique`, `idx_batch_faskes_expired`, dst.). Index pendamping FK
   (`*_foreign`) tidak ditulis ganda karena dibuat otomatis oleh MySQL.
5. Tabel warisan (`settings`, view, 4 tabel yatim, kolom `jenis_pengajuan`, index `idx_pemakaian_header_v2`)
   tidak dibuat (§4.1).
6. Migrasi skeleton disesuaikan agar identik dengan referensi: `users` (+3 kolom), `jobs.attempts`
   `unsignedSmallInteger`, `failed_jobs.connection/queue` `string(255)` + index gabungan, `notifications`.

### 4.4 Verifikasi paritas skema (wajib sebelum copy Models/Services)

Verifier ada di repo: `php database/schema-diff.php [--target=sqlite|mysql]`. Default **sqlite**
(`database/database.sqlite`); `--sqlite=path` untuk file lain; `--target=mysql` membandingkan ke `obat_v2`.
Exit code `0` = tidak ada selisih, `1` = ada.

| Cek | MySQL (referensi) | SQLite (target dev) |
| --- | --- | --- |
| Kolom | `information_schema.columns` | `PRAGMA table_info` + normalisasi tipe |
| Tipe | `column_type` | `bigint unsigned`→`integer`, `enum`→`varchar`, `decimal`→`numeric`, `boolean`→`integer`, `json`→`text`, `timestamp`→`datetime` |
| Nullable/default | `is_nullable`, `column_default` | `notnull`, `dflt_value` (default dinormalkan, `'0'`→`0`) |
| Index | nama, uniqueness, urutan kolom | `PRAGMA index_list` + `index_info`; `sqlite_autoindex_*` diabaikan |
| Foreign key | constraint + aturan | `PRAGMA foreign_key_list`; **nama constraint tidak dibandingkan**, `RESTRICT`≈`NO ACTION`, index `*_foreign` dilewati |
| Tabel | 55 | yang belum ada dilaporkan (bukan gagal) |

Selisih yang **sengaja** di-whitelist: view, `idx_pemakaian_header_v2`, `laporan_lplpo.jenis_pengajuan`,
tabel `settings`.

**Hasil 16 Nov 2026** (kedua mode):

| Mode | Tabel | Selisih | Exit |
| --- | --- | --- | --- |
| `--target=sqlite` | 48 / 55 | 0 | 0 |
| `--target=mysql` | 48 / 55 | 0 | 0 |

Tabel tersisa yang dilaporkan: `permissions`, `roles`, `model_has_permissions`, `model_has_roles`,
`role_has_permissions`, `activity_log` (menunggu package).

### 4.5 Seeding (karena DB kosong)

| Kebutuhan | Seeder |
| --- | --- |
| 5 role + permission | `RoleAndPermissionSeeder` |
| Master data | `FaskesSeeder`, `ObatSeeder`, `SumberDanaSeeder`, `SupplierSeeder` |
| Akun login | `UserSeeder` / `E2eTestUserSeeder` |
| Data transaksi untuk AI & laporan | `SimulasiTransaksiSeeder` + `GenerateDummyDataCommand` |
| Tanpa dummy | `AiTrainModels` berstatus `data_belum_cukup` — dashboard AI kosong |

### Checklist

- [x] SQLite dev dibuat (`database/database.sqlite`), `.env` menunjuk ke sana, WAL + `busy_timeout` aktif
- [x] 10 migrasi sistem + 38 migrasi domain ada, tanpa error FK
- [x] `php artisan migrate:fresh` sukses (skema reproducible)
- [x] `php database/schema-diff.php` → 48 tabel, 0 selisih, exit 0 (mode sqlite)
- [x] `php database/schema-diff.php --target=mysql` → 0 selisih (mode mysql)
- [x] `phpunit.xml` force `sqlite :memory:`, `php artisan test` hijau
- [ ] `vendor:publish` spatie permission + activitylog → 54 tabel
- [ ] `php artisan db:seed` (master + role + akun) berhasil
- [ ] Model inti bisa query: `Obat`, `FasilitasKesehatan`, `BatchStok`, `PemakaianObat`, `User`
- [ ] Login dengan akun hasil seeder berhasil
- [ ] `git init` + commit awal ada

> **Catatan:** DB dev tidak berisi data produksi, jadi `migrate:fresh` adalah alur normal (bukan pantangan).
> `obat` tetap tidak boleh diubah — perlakukan sebagai read-only reference.

---

## 5. Fase 1 — Fondasi aplikasi

### 5.1 Paket Composer

```powershell
composer require spatie/laravel-permission spatie/laravel-activitylog `
  spatie/laravel-pdf spatie/browsershot dompdf/dompdf `
  phpoffice/phpspreadsheet laravel/socialite spatie/laravel-backup
```

Versi acuan (dari `D:\project\obat\composer.json`): permission ^7.4, activitylog ^5.0, laravel-pdf ^2.8,
browsershot *, dompdf ^3.1, phpspreadsheet ^5.8, socialite ^5.5, backup ^10.3.

Dev: `laravel/pint` sudah ada.

**Keputusan test runner (harus diputuskan sebelum Fase 1):** skeleton memakai **PHPUnit 12**
(`phpunit.xml` + `phpunit/phpunit`). Plan ini memilih **Pest** → install di awal Fase 1 dan
konversi test bawaan:

```powershell
composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies
php artisan pest:install
# lalu hapus/arsipkan phpunit.xml, dan ubah "composer test" ke ./vendor/bin/pest
```

Jika ingin minim risiko, alternatifnya tetap PHPUnit 12 dan abaikan §13 poin 7 versi Pest — pilih salah
satu, jangan dua-duanya.

**Catatan dependency cetak:** `spatie/laravel-pdf` + `browsershot` memerlukan **Node + Puppeteer/Chrome**
di mesin dev & server (project lama sudah memakainya). `dompdf` disimpan sebagai fallback.

### 5.2 Reuse kode dari project lama

| Sumber | Aksi |
| --- | --- |
| `app/Models` (37 model + `Traits/LogsActivity.php`) | copy; `User.php` satu-satunya yang implement Filament (`HasAvatar`) → buang method `getFilamentAvatarUrl()` |
| `app/Services` (24: 20 root + `Ann/` 3 + `Laporan/` 1) | copy; bersihkan Filament dari **`BuatPermintaanService`** & **`NotificationService`** (`Filament\Actions\Action`, `Filament\Notifications\Notification`). `StokService`/`FefoService` sudah bersih (0 referensi) |
| `app/Policies` (24) | copy; hanya **`ModelPrediksiPolicy`** & **`PrediksiKebutuhanPolicy`** yang import `App\Filament\Pages\PrediksiAiPage` → ganti ke permission/route sendiri |
| `app/Enums` (1), `app/Helpers` (1) | copy (`MetodeStok`, `BatchNumberGenerator`) |
| `app/Support` | **tidak dibawa** — isinya hanya `ModalRepeaterColumn.php` (murni `filament-modal-repeater`) |
| `app/Http/Controllers` cetak/auth | copy 10 controller `Cetak*` + `CustomAuthController`, `GoogleSocialiteController`, `PanduanController`, `DownloadSuratPermintaanController`; semua flat, susun ulang ke subfolder §3 |
| `app/Console/Commands` (21) | copy (AI train, 6 command tutup buku, stok minimum, cleanup, docs, dll.) |
| `routes/console.php` | copy scheduler (AiTrainModels weekly, stok minimum monthly, backup:clean/run) |
| `resources/views/pdf/*` (8) | copy; cek dependency `spatie/laravel-pdf` |
| `database/migrations` (96 lama) | **tidak disalin**; jadi referensi penulisan 38 migrasi domain baru (§4.3) |
| `database/seeders` (11), `factories` | copy selektif — utamakan `RoleAndPermissionSeeder`, `FaskesSeeder`, `SumberDanaSeeder`, `SupplierSeeder` |
| `app/Filament` (23 Resource, 182 file), `app/Livewire` (1) | **tidak dipakai** → jadikan checklist paritas §14 |

> Seed `RoleAndPermissionSeeder` sudah berisi 5 role (`super_admin`, `admin_gudang`, `admin_dinas`,
> `puskesmas`, `pustu`) + permission granular — tinggal dijalankan, tidak perlu ditulis ulang.

### 5.3 Layout & Alpine stores

`resources/js/app.js`:

```js
import './bootstrap';
import Alpine from 'alpinejs';
import docForm from './components/docForm';
import tableFilter from './components/tableFilter';
import chart from './components/chart';

window.Alpine = Alpine;
Alpine.data('docForm', docForm);
Alpine.data('tableFilter', tableFilter);
Alpine.data('chart', chart);
Alpine.store('ui', { sidebarOpen: true, dark: false });
Alpine.store('toast', { items: [], push(msg, type = 'success') { /* ... */ } });
Alpine.start();
```

`layouts/app.blade.php`: sidebar collapsible (role-aware menu), topbar (search, notifikasi, profil), flash message → toast, `@vite`.

### 5.4 Auth & otorisasi

- Port `CustomAuthController` (login/logout) + `login.blade.php`.
- Port `GoogleSocialiteController` (opsional, `users.google_login_enabled`).
- Seeder role: `super_admin`, `admin_gudang`, `admin_dinas`, `puskesmas`, `pustu` (sudah ada di `RoleAndPermissionSeeder`).
- Middleware `role:` + `Gate::before` untuk `super_admin`.
- Policy dipanggil di controller (`$this->authorize(...)`), bukan di UI.
- **Skema `users` tidak punya kolom `is_active`.** Kolom yang tersedia: `fasilitas_kesehatan_id`,
  `google_login_enabled`, `last_active_at`, `email_verified_at`. Jadi aktivasi/nonaktivasi akun harus
  lewat role/permission, bukan flag `is_active`.
- Akun uji dibuat oleh `UserSeeder` / `E2eTestUserSeeder` — tidak ada user hasil clone.

### Checklist

- [ ] `php artisan serve` + login berhasil
- [ ] Menu sidebar tampil sesuai role
- [ ] `npm run dev` jalan, HMR aktif
- [ ] `php artisan test` hijau (test dasar)

---

## 6. Fase 2 — Master data

| Modul | Route | Fitur |
| --- | --- | --- |
| Obat | `/obat` | list + filter kategori/status/metode, create/edit, nonaktifkan, import CSV, export Excel |
| Fasilitas | `/fasilitas` | list puskesmas/pustu, induk puskesmas, status |
| Sumber Dana | `/sumber-dana` | CRUD, tahun, pagu |
| Supplier | `/supplier` | CRUD |
| User & Role | `/users`, `/roles` | CRUD, assign role, reset password |
| Pengaturan Laporan | `/pengaturan/laporan` | kop surat, TTD, format nomor, PDF (per faskes) |
| Preferensi | `/profil` | avatar, bahasa, sidebar/topbar, item per halaman |

Pola halaman: `Controller@index` (paginate + filter query string) → `index.blade.php`; modal create/edit Alpine; validasi `FormRequest`; error ditampilkan via `@error`.

**Import/Export** ditulis sendiri: `ImportObatJob` (queue) + progres di tabel `imports`/`exports`
(keduanya dibuat oleh migration Fase 0, lihat §4.1) — pakai ulang skemanya, jangan bikin tabel baru.

---

## 7. Fase 3 — Stok & batch

| Modul | Isi |
| --- | --- |
| Stok Gudang | agregat per obat, stok minimum, drill-down batch |
| Stok Faskes | per faskes + obat, drill-down batch + filter expired |
| Batch | daftar batch per faskes/gudang, status, expiry, nilai |
| Riwayat Stok | log mutasi dengan filter tipe/tanggal/obat |
| Opname | header + detail (stok sistem vs fisik), status draft→proses→selesai |
| Stok minimum | jalankan `KalkulasiStokMinimumCommand`, tampilkan ringkasan |

Semua penulisan stok **wajib** lewat `StokService` (batch = sumber kebenaran).

---

## 8. Fase 4 — Transaksi inti (paling berat)

| Modul | Alur | Kompleksitas |
| --- | --- | --- |
| Penerimaan | draft → dikonfirmasi (buat/tambah batch, riwayat, harga) → batal (reverse) | M |
| Permintaan | draft → menunggu_persetujuan → disetujui/ditolak → sedang_didistribusi → diterima | M |
| Distribusi | pilih batch (FEFO/FIFO/LIFO via `obat.metode_stok`), split allocation, kirim → diterima (auto buat penerimaan) | L |

Karena tanpa Livewire, form dokumen memakai modul Alpine bersama:

```js
// resources/js/components/docForm.js
export default ({ rows = [], obatUrl, batchUrl, fasilitasId = null, mode = 'create' }) => ({
    rows,
    addRow() { /* ... */ },
    removeRow(i) { /* ... */ },
    get total() { return this.rows.reduce((s, r) => s + (r.jumlah || 0), 0); },
    async cariObat(i, q) { /* fetch(obatUrl) */ },
    async pilihBatch(i, obatId) { /* fetch(batchUrl + '?obat_id=..&fasilitas_id=..') */ },
});
```

Submit tetap `POST` biasa dengan `details[i][obat_id]` dst., sehingga validasi server & `old()` bekerja normal.

---

## 9. Fase 5 — Pemakaian & retur

- **Pemakaian**: header (faskes, tanggal, jenis pelayanan, pasien, diagnosa) + detail (obat, batch FEFO, dosis). Sumber data training AI.
- **Retur**: internal (puskesmas→gudang, pustu→puskesmas) & ke supplier; status draft → menunggu_approval → disetujui → dalam_pengiriman → diterima → selesai.
- **Inspeksi retur**: hasil + tindakan per baris.

---

## 10. Fase 6 — Pelaporan, perencanaan & cut-off

| Modul | Isi |
| --- | --- |
| LPLPO | header per faskes/periode + detail; verifikasi Dinas (`status_verifikasi`) |
| RKO | rencana tahunan, detail ABC/VEN, buffer, prediksi referensi |
| Neraca Tahunan | header + detail + rincian per sumber dana |
| Tutup Buku | inisial/tahunan, deadline, reminder, buka kembali |
| Alokasi Dana | perencanaan/realisasi/alokasi per sumber dana |
| Cetak | PDF LPLPO, RKO, neraca, faktur distribusi/penerimaan/permintaan/retur; Excel obat & RKO |

---

## 11. Fase 7 — Prediksi AI & dashboard

- Halaman prediksi: rekomendasi per obat, KPI akurasi, lonjakan, detail.
- Training via `AiTrainModels` (scheduler) + status model (`aktif`, `kadaluarsa`, `gagal`, `data_belum_cukup`).
- Dashboard per role:
  - **Dinas**: KPI stok, alert kritis, tren distribusi, insight AI, status puskesmas.
  - **Puskesmas/Pustu**: ringkasan stok, pemakaian terbaru, permintaan berjalan.
- Chart.js untuk tren pemakaian, prediksi vs realisasi, status model.

---

## 12. Fase 8 — Backlog fitur baru (setelah paritas)

Prioritas P1 (dari analisis skema):

1. **Telusur batch end-to-end** (supplier → gudang → faskes → pemakaian).
2. **Karantina & pemusnahan batch** + berita acara.
3. **Alert kadaluarsa terpusat** (<3/6/12 bulan + nilai rupiah).
4. **Dashboard SLA & fill rate** distribusi.
5. **Inbox "Tugas Saya"** (approval, verifikasi, inspeksi, opname, tutup buku).
6. **Inspeksi retur mempengaruhi stok** — terverifikasi 15 Nov 2026: `InspeksiRetur` hanya dipakai di
   Model/Policy/Resource, **tidak ada** referensi di `app/Services` maupun `app/Console/Commands`, jadi
   hasil inspeksi (`hasil_inspeksi`, `tindakan`) memang tidak mengubah stok.
7. **Penyerapan anggaran per sumber dana** (pagu vs realisasi vs nilai stok).
8. **Monitoring akurasi model** (MAPE aktual vs realisasi).
9. **Dashboard kepatuhan tutup buku**.
10. **Health-check data** (orphan batch, selisih agregat, stok negatif).

Lainnya (P2/P3) tercatat di diskusi: redistribusi antar faskes, ABC–VEN, analitik diagnosa, notifikasi email/browser, i18n, audit per dokumen.

---

## 13. Konvensi kode

1. **Controller tipis** — validasi di `FormRequest`, logika di `Service`, otorisasi via Policy.
2. **Satu halaman = satu Blade** + partial; filter/sort lewat query string (bukan state JS).
3. **Alpine hanya untuk interaksi**: sidebar, modal, baris dinamis, chart, toast. Tidak menyimpan data bisnis —
   state baris di `docForm.js` hanya state form sementara, bukan sumber kebenaran (server tetap validasi ulang).
4. **JSON endpoint** hanya untuk lookup (`/api/obat/search`, `/api/batch`) — read-only, dijaga `auth` + policy.
5. **Penulisan stok** selalu via `StokService`; tidak ada update `stok_gudang`/`stok_faskes` langsung.
6. **Format**: `laravel/pint` sebelum commit.
7. **Test**: Pest — feature test untuk tiap alur status dokumen; unit test untuk `StokService`/`FefoService`.
   Jika Pest dibatalkan di §5.1, ganti dengan PHPUnit 12 (skeleton) dan tetap pertahankan cakupan test yang sama.
8. **Skema hanya lewat migration**: tidak ada perubahan struktur DB manual (`ALTER` di phpMyAdmin/CLI).
   DB `obat` adalah **read-only reference** dan tidak boleh diubah dari project ini.
9. **SQL portabel (SQLite dev + MySQL produksi)**: hindari fungsi MySQL-only mentah (`DATE_FORMAT`, `YEAR()`,
   `GROUP_CONCAT`, `IFNULL`, `SET FOREIGN_KEY_CHECKS`). Pakai query builder/Carbon; bila butuh raw, sediakan
   cabang `DB::connection()->getDriverName() === 'sqlite'` (contoh: `strftime` vs `DATE_FORMAT`).
10. **Uang tetap `decimal`**, tapi jangan assert nilai uang persis di test — SQLite menyimpannya sebagai angka
    floating; bulatkan/toleransi sebelum membandingkan.

---

## 14. Checklist paritas modul (acuan dari Filament lama)

- [ ] Obats
- [ ] FasilitasKesehatans
- [ ] SumberDanas
- [ ] Suppliers
- [ ] Users
- [ ] Roles
- [ ] Permissions
- [ ] ActivityLogs
- [ ] StokGudangs
- [ ] StokFaskes
- [ ] RiwayatStoks
- [ ] OpnameStoks
- [ ] PenerimaanStoks
- [ ] DistribusiObats
- [ ] PermintaanObats
- [ ] PemakaianObats
- [ ] ReturObats
- [ ] InspeksiReturs
- [ ] LaporanLplpos
- [ ] LaporanRkos
- [ ] NeracaTahunans
- [ ] TutupBuku
- [ ] AlokasiDana
- [ ] DashboardAi
- [ ] Cluster Pengaturan (laporan, PDF, format nomor)
- [ ] Halaman Panduan
- [ ] Cetak PDF/Excel (10 controller `Cetak*`)

---

## 15. Risiko & catatan

| Risiko | Mitigasi |
| --- | --- |
| Form dokumen multi-baris tanpa Livewire | Satu modul `docForm.js` reusable; submit POST biasa + validasi server |
| Import/Export Filament hilang | Tulis job + progress sendiri sejak Fase 2 (tabel `imports`/`exports` sudah ada dari §4.1) |
| Drift skema migration vs Model/Service lama | `php database/schema-diff.php` wajib 0 selisih **sebelum** copy Models/Services; `obat` read-only sebagai referensi |
| Beda perilaku SQLite vs MySQL | (a) `useCurrentOnUpdate()` diabaikan → `updated_at` tidak auto-bump pada `DB::table()->update()`; (b) `decimal` jadi floating point → jangan assert uang persis; (c) tidak ada row lock (`lockForUpdate` no-op). Mitigasi: enum/index/FK tetap diverifikasi `schema-diff`, dan uji akhir tetap di MySQL |
| `database is locked` (SQLite) | WAL + `busy_timeout=5000` sudah aktif di `config/database.php`; hindari menjalankan dua proses tulis berat bersamaan |
| Raw SQL MySQL-only di service lama | Terverifikasi hanya 3 tempat, dan 3 sudah punya cabang driver. Sisa 1: `SimulasiDummyService:1520` (`DATE_FORMAT(tanggal, '%Y-%m')`) → ganti `strftime('%Y-%m', tanggal)` saat copy |
| Token GitHub Composer invalid (401) | Mirror global Tencent sudah terpasang (`repositories.0` = mirrors.tencent.com/composer); perbarui token agar kembali ke Packagist resmi |
| Cetak PDF butuh Node/Puppeteer | `browsershot` sudah dipakai project lama, tapi pastikan Node+Chrome ada di mesin/server baru; `dompdf` sebagai fallback |
| Dua salinan `Struktur Database.md` | Ada di `D:\project\obat\docs\` **dan** root project baru → jadikan `docs/` sumber tunggal, hapus salinan root agar tidak drift |
| Warisan skema lama | (a) index duplikat `pemakaian_obat` (`idx_pemakaian_faskes_obat` = `idx_pemakaian_header_v2`), (b) kolom mati `laporan_lplpo.jenis_pengajuan`, (c) view `stock_summary_view` rusak (join ke `stock`/`items`/`item_categories` yang tidak ada), (d) 4 tabel yatim (`failed_import_rows`, `import_data_historis`, `pengaturan_laporan_new/_old`) — **(a)–(d) otomatis tidak ada** karena skema ditulis baru (§4.1); (e) `inspeksi_retur` tidak menyentuh stok → tetap backlog §12 no. 6 |
| Data dev kosong setelah `migrate` | Jalankan seeder master + `SimulasiTransaksiSeeder`/`GenerateDummyDataCommand` (§4.5); tanpa itu fitur AI & laporan belum bisa diuji |

---

## 16. Langkah berikutnya (urut)

1. **Fase 0a** ✅: 43 migration file + `migrate` di SQLite (48 tabel, WAL aktif) + `schema-diff` 0 selisih.
2. **Fase 0b**: `git init` + commit awal; `.env` sudah ke SQLite (`database/database.sqlite`), `phpunit.xml` sudah `:memory:`.
3. **Fase 0c**: install paket → `vendor:publish` spatie permission/activitylog (6 tabel) → `db:seed` master + akun → `schema-diff` final 0 selisih (sqlite & mysql).
4. **Fase 1a**: install paket Composer (+ putuskan Pest/PHPUnit) + layout & auth + seeder role.
5. **Fase 1b**: copy Models (37) / Services (24) / Policies (24) / Commands (21) / 10 controller cetak, bersihkan 5 titik Filament (`User`, `BuatPermintaanService`, `NotificationService`, `ModelPrediksiPolicy`, `PrediksiKebutuhanPolicy`), perbaiki `SimulasiDummyService:1520`, lalu smoke test login.
6. **Fase 2**: modul Obat sebagai pola acuan (list/filter/modal/import) sebelum modul lain.
