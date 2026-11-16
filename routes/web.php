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
use App\Http\Controllers\Transaksi\PenerimaanController;
use App\Http\Controllers\Transaksi\PermintaanController;
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

    Route::prefix('api')->middleware('throttle:60,1')->group(function (): void {
        Route::get('obat/search', [LookupController::class, 'obat'])->name('api.obat.search');
        Route::get('batch', [LookupController::class, 'batch'])->name('api.batch');
        Route::get('stok-sistem', [LookupController::class, 'stokSistem'])->name('api.stok-sistem');
    });
});
