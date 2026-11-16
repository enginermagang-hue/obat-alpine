<?php

use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CetakObatExcelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Master\FasilitasKesehatanController;
use App\Http\Controllers\Master\ImportObatController;
use App\Http\Controllers\Master\ObatController;
use App\Http\Controllers\Master\RoleController;
use App\Http\Controllers\Master\SumberDanaController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\Stok\BatchController;
use App\Http\Controllers\Stok\OpnameController;
use App\Http\Controllers\Stok\RiwayatStokController;
use App\Http\Controllers\Stok\StokFaskesController;
use App\Http\Controllers\Stok\StokGudangController;
use App\Http\Controllers\CetakFakturController;
use App\Http\Controllers\CetakFakturPenerimaanController;
use App\Http\Controllers\CetakFakturReturController;
use App\Http\Controllers\CetakLplpoController;
use App\Http\Controllers\CetakNeracaController;
use App\Http\Controllers\CetakPermintaanController;
use App\Http\Controllers\CetakRkoController;
use App\Http\Controllers\DownloadSuratPermintaanController;
use App\Http\Controllers\Laporan\AlokasiDanaController;
use App\Http\Controllers\Laporan\LplpoController;
use App\Http\Controllers\Laporan\NeracaController;
use App\Http\Controllers\Laporan\PengaturanLaporanController;
use App\Http\Controllers\Laporan\RkoController;
use App\Http\Controllers\Laporan\TutupBukuController;
use App\Http\Controllers\Transaksi\DistribusiController;
use App\Http\Controllers\Transaksi\InspeksiReturController;
use App\Http\Controllers\Transaksi\PemakaianController;
use App\Http\Controllers\Transaksi\PenerimaanController;
use App\Http\Controllers\Transaksi\PermintaanController;
use App\Http\Controllers\Transaksi\ReturController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.post');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('profil', [ProfilController::class, 'edit'])->name('profil.edit');
    Route::put('profil', [ProfilController::class, 'update'])->name('profil.update');
    Route::put('profil/password', [ProfilController::class, 'updatePassword'])->name('profil.password');

    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
    Route::post('auth/google/unlink', [ProfilController::class, 'unlinkGoogle'])->name('google.unlink');

    Route::get('obat/export', CetakObatExcelController::class)->name('obat.export');
    Route::post('obat/import', [ImportObatController::class, 'store'])->name('obat.import');
    Route::get('obat/import/template', [ImportObatController::class, 'template'])->name('obat.import.template');
    Route::patch('obat/{obat}/deactivate', [ObatController::class, 'deactivate'])->name('obat.deactivate');
    Route::resource('obat', ObatController::class)->except('show');

    Route::patch('fasilitas/{fasilitas}/deactivate', [FasilitasKesehatanController::class, 'deactivate'])->name('fasilitas.deactivate');
    Route::resource('fasilitas', FasilitasKesehatanController::class)
        ->parameters(['fasilitas' => 'fasilitas'])
        ->except('show');

    Route::patch('sumber-dana/{sumber_dana}/deactivate', [SumberDanaController::class, 'deactivate'])->name('sumber-dana.deactivate');
    Route::resource('sumber-dana', SumberDanaController::class)->except('show');

    Route::patch('supplier/{supplier}/deactivate', [SupplierController::class, 'deactivate'])->name('supplier.deactivate');
    Route::resource('supplier', SupplierController::class)->except('show');

    Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::resource('users', UserController::class)->except('show');

    Route::resource('roles', RoleController::class)->except('show');

    Route::post('stok-gudang/recalculate', [StokGudangController::class, 'recalculate'])->name('stok-gudang.recalculate');
    Route::get('stok-gudang/{obat}', [StokGudangController::class, 'show'])->name('stok-gudang.show');
    Route::resource('stok-gudang', StokGudangController::class)->only('index');

    Route::post('stok-faskes/recalculate', [StokFaskesController::class, 'recalculate'])->name('stok-faskes.recalculate');
    Route::get('stok-faskes/{fasilitas}/{obat}', [StokFaskesController::class, 'show'])->name('stok-faskes.show');
    Route::resource('stok-faskes', StokFaskesController::class)->only('index');

    Route::resource('batch', BatchController::class)->only('index');

    Route::resource('riwayat-stok', RiwayatStokController::class)->only('index');

    Route::patch('opname/{opname}/reopen', [OpnameController::class, 'reopen'])->name('opname.reopen');
    Route::resource('opname', OpnameController::class);

    Route::patch('penerimaan/{penerimaan}/confirm', [PenerimaanController::class, 'confirm'])->name('penerimaan.confirm');
    Route::patch('penerimaan/{penerimaan}/cancel', [PenerimaanController::class, 'cancel'])->name('penerimaan.cancel');
    Route::resource('penerimaan', PenerimaanController::class);

    Route::patch('permintaan/{permintaan}/submit', [PermintaanController::class, 'submit'])->name('permintaan.submit');
    Route::patch('permintaan/{permintaan}/approve', [PermintaanController::class, 'approve'])->name('permintaan.approve');
    Route::patch('permintaan/{permintaan}/reject', [PermintaanController::class, 'reject'])->name('permintaan.reject');
    Route::resource('permintaan', PermintaanController::class);

    Route::patch('distribusi/{distribusi}/kirim', [DistribusiController::class, 'kirim'])->name('distribusi.kirim');
    Route::patch('distribusi/{distribusi}/batalkan-pengiriman', [DistribusiController::class, 'batalkanPengiriman'])->name('distribusi.batalkan-pengiriman');
    Route::patch('distribusi/{distribusi}/tolak', [DistribusiController::class, 'tolak'])->name('distribusi.tolak');
    Route::post('distribusi/{distribusi}/buat-penerimaan', [DistribusiController::class, 'buatPenerimaan'])->name('distribusi.buat-penerimaan');
    Route::resource('distribusi', DistribusiController::class);

    Route::resource('pemakaian', PemakaianController::class);

    Route::resource('inspeksi-retur', InspeksiReturController::class)->parameters(['inspeksi-retur' => 'inspeksi_retur']);

    Route::patch('lplpo/{lplpo}/submit', [LplpoController::class, 'submit'])->name('lplpo.submit');
    Route::patch('lplpo/{lplpo}/verify', [LplpoController::class, 'verify'])->name('lplpo.verify');
    Route::patch('lplpo/{lplpo}/request-revision', [LplpoController::class, 'requestRevision'])->name('lplpo.request-revision');
    Route::post('lplpo/{lplpo}/revise', [LplpoController::class, 'revise'])->name('lplpo.revise');
    Route::patch('lplpo/{lplpo}/regenerate', [LplpoController::class, 'regenerate'])->name('lplpo.regenerate');
    Route::resource('lplpo', LplpoController::class);

    Route::get('cetak/lplpo/{lplpo}', CetakLplpoController::class)->name('lplpo.cetak');

    Route::patch('rko/{rko}/submit', [RkoController::class, 'submit'])->name('rko.submit');
    Route::patch('rko/{rko}/approve', [RkoController::class, 'approve'])->name('rko.approve');
    Route::patch('rko/{rko}/reject', [RkoController::class, 'reject'])->name('rko.reject');
    Route::resource('rko', RkoController::class);

    Route::get('cetak/rko/{rko}', [CetakRkoController::class, 'cetakPdf'])->name('rko.cetak-pdf');
    Route::get('cetak/rko/{rko}/xls', [CetakRkoController::class, 'cetakXls'])->name('rko.cetak-xls');

    Route::patch('neraca/{neraca}/finish', [NeracaController::class, 'finish'])->name('neraca.finish');
    Route::patch('neraca/{neraca}/reopen', [NeracaController::class, 'reopen'])->name('neraca.reopen');
    Route::resource('neraca', NeracaController::class)->except('edit', 'update');

    Route::get('cetak/neraca/{neraca}', [CetakNeracaController::class, 'cetakPdf'])->name('neraca.cetak-pdf');
    Route::get('cetak/neraca/{neraca}/xls', [CetakNeracaController::class, 'cetakXls'])->name('neraca.cetak-xls');

    Route::get('cetak/distribusi/{distribusi}', CetakFakturController::class)->name('distribusi.cetak');
    Route::get('cetak/penerimaan/{penerimaan}', CetakFakturPenerimaanController::class)->name('penerimaan.cetak');
    Route::get('cetak/retur/{retur}', CetakFakturReturController::class)->name('retur.cetak');
    Route::get('cetak/permintaan/{permintaan}', CetakPermintaanController::class)->name('permintaan.cetak');
    Route::get('permintaan/{permintaan}/surat', DownloadSuratPermintaanController::class)->name('permintaan.surat');

    Route::post('tutup-buku', [TutupBukuController::class, 'store'])->name('tutup-buku.store');
    Route::patch('tutup-buku/{tutupBuku}/buka-kembali', [TutupBukuController::class, 'bukaKembali'])->name('tutup-buku.buka-kembali');
    Route::patch('tutup-buku/{tutupBuku}/tutup-kembali', [TutupBukuController::class, 'tutupKembali'])->name('tutup-buku.tutup-kembali');
    Route::get('tutup-buku', [TutupBukuController::class, 'index'])->name('tutup-buku.index');

    Route::get('alokasi-dana', [AlokasiDanaController::class, 'index'])->name('alokasi-dana.index');

    Route::get('pengaturan-laporan', [PengaturanLaporanController::class, 'index'])->name('pengaturan-laporan.index');
    Route::patch('pengaturan-laporan/rko', [PengaturanLaporanController::class, 'updateGlobal'])->name('pengaturan-laporan.global');
    Route::patch('pengaturan-laporan/rko/faskes/{faskes}', [PengaturanLaporanController::class, 'updateFaskes'])->name('pengaturan-laporan.faskes');

    Route::patch('retur/{retur}/submit', [ReturController::class, 'submit'])->name('retur.submit');
    Route::patch('retur/{retur}/approve', [ReturController::class, 'approve'])->name('retur.approve');
    Route::patch('retur/{retur}/reject', [ReturController::class, 'reject'])->name('retur.reject');
    Route::patch('retur/{retur}/send', [ReturController::class, 'send'])->name('retur.send');
    Route::patch('retur/{retur}/receive', [ReturController::class, 'receive'])->name('retur.receive');
    Route::patch('retur/{retur}/complete', [ReturController::class, 'complete'])->name('retur.complete');
    Route::resource('retur', ReturController::class);

    Route::prefix('api')->middleware('throttle:60,1')->group(function (): void {
        Route::get('obat/search', [LookupController::class, 'obat'])->name('api.obat.search');
        Route::get('batch', [LookupController::class, 'batch'])->name('api.batch');
        Route::get('stok-sistem', [LookupController::class, 'stokSistem'])->name('api.stok-sistem');
        Route::get('distribusi/alokasi', [LookupController::class, 'alokasi'])->name('api.distribusi.alokasi');
    });
});
