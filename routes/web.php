<?php

use App\Http\Controllers\Gudang\AcaraController;
use App\Http\Controllers\Gudang\BarangController;
use App\Http\Controllers\Gudang\DashboardController;
use App\Http\Controllers\Gudang\LaporanController;
use App\Http\Controllers\Gudang\PengembalianController;
use App\Http\Controllers\Gudang\RestockController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

// ---------- PWA manifest ----------
Route::get('/manifest.webmanifest', function () {
    $isi = is_file(public_path('manifest.webmanifest'))
        ? file_get_contents(public_path('manifest.webmanifest'))
        : '{}';

    return response($isi, 200, ['Content-Type' => 'application/manifest+json']);
});

// ---------- Tamu ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// ---------- Login wajib ----------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Scan QR — boleh admin & pekerja
    Route::get('/b/{kode}', [BarangController::class, 'scan'])->name('scan');
    Route::get('/scan', [BarangController::class, 'scanPage'])->name('scanpage');

    // ---------- Khusus admin ----------
    Route::middleware('role:admin')->group(function () {
        // Dashboard
        Route::get('/', [DashboardController::class, 'index']);

        // Barang
        Route::get('/barang', [BarangController::class, 'form'])->name('barang');
        Route::post('/barang', [BarangController::class, 'store']);
        Route::get('/barang/{id}/edit', [BarangController::class, 'edit']);
        Route::get('/barang/{id}/riwayat', [BarangController::class, 'riwayat']);
        Route::put('/barang/{id}', [BarangController::class, 'update']);
        Route::post('/barang/{id}/kurangi', [BarangController::class, 'kurangi']);
        Route::post('/barang/{id}/tambah', [BarangController::class, 'tambah']);
        Route::delete('/barang/{id}', [BarangController::class, 'destroy']);

        // Restock / stok
        Route::get('/restock', [RestockController::class, 'form'])->name('restock');
        Route::post('/restock', [RestockController::class, 'store']);
        Route::post('/rusak', [RestockController::class, 'rusak']);
        Route::post('/stok/sinkron', [RestockController::class, 'sinkron']);

        // Acara
        Route::get('/acara', [AcaraController::class, 'form'])->name('acara');
        Route::post('/acara', [AcaraController::class, 'store']);
        Route::get('/acara/{id}/edit', [AcaraController::class, 'edit']);
        Route::put('/acara/{id}', [AcaraController::class, 'update']);
        Route::post('/acara/{id}/konfirmasi', [AcaraController::class, 'konfirmasi']);
        Route::post('/acara/{id}/batalkan', [AcaraController::class, 'batalkan']);

        // Pengembalian
        Route::get('/pengembalian', [PengembalianController::class, 'form'])->name('pengembalian');
        Route::post('/pengembalian/preview', [PengembalianController::class, 'preview']);
        Route::post('/pengembalian', [PengembalianController::class, 'store']);
        Route::post('/pengembalian/{id}/undo', [PengembalianController::class, 'undo']);

        // Proses (barang sedang di acara)
        Route::get('/proses', [AcaraController::class, 'prosesIndex'])->name('proses');
        Route::get('/proses/{id}', [AcaraController::class, 'prosesDetail'])->whereNumber('id');

        // Kategori
        Route::get('/kategori', [LaporanController::class, 'kategoriIndex'])->name('kategori');
        Route::post('/kategori', [LaporanController::class, 'kategoriStore']);
        Route::put('/kategori/{id}', [LaporanController::class, 'kategoriUpdate']);
        Route::delete('/kategori/{id}', [LaporanController::class, 'kategoriDestroy']);

        // Riwayat & ekspor
        Route::get('/riwayat', [LaporanController::class, 'riwayat'])->name('riwayat');
        Route::get('/riwayat/pdf', [LaporanController::class, 'riwayatPdf']);
        Route::get('/riwayat/excel', [LaporanController::class, 'riwayatExcel']);
        Route::get('/stok/excel', [LaporanController::class, 'stokExcel']);
        Route::get('/riwayat/{id}', [LaporanController::class, 'riwayatDetail'])->whereNumber('id');

        // Log aktivitas
        Route::get('/log', [LaporanController::class, 'log'])->name('log');
        Route::get('/log/export', [LaporanController::class, 'logExport']);

        // QR
        Route::get('/qr', [BarangController::class, 'qrPage'])->name('qr');

        // Kelola user
        Route::get('/users', [LoginController::class, 'indexUsers']);
        Route::post('/users', [LoginController::class, 'storeUser']);
        Route::put('/users/{id}', [LoginController::class, 'updateUser']);
        Route::delete('/users/{id}', [LoginController::class, 'destroyUser']);
    });
});
