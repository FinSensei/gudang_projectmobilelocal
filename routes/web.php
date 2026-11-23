<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GudangController;

Route::get('/', [GudangController::class, 'index']);

Route::get('/barang', [GudangController::class, 'barangForm'])->name('barang');
Route::post('/barang', [GudangController::class, 'barangStore']);
Route::get('/barang/{id}/edit', [GudangController::class, 'barangEdit']);
Route::put('/barang/{id}', [GudangController::class, 'barangUpdate']);
Route::post('/barang/{id}/kurangi', [GudangController::class, 'barangKurangi']);
Route::post('/barang/{id}/tambah', [GudangController::class, 'barangTambah']);
Route::delete('/barang/{id}', [GudangController::class, 'barangDestroy']);

Route::get('/restock', [GudangController::class, 'restockForm'])->name('restock');
Route::post('/restock', [GudangController::class, 'restockStore']);

Route::post('/rusak', [GudangController::class, 'rusakStore']);

Route::get('/acara', [GudangController::class, 'acaraForm'])->name('acara');
Route::post('/acara', [GudangController::class, 'acaraStore']);

Route::get('/pengembalian', [GudangController::class, 'pengembalianForm'])->name('pengembalian');
Route::post('/pengembalian/preview', [GudangController::class, 'pengembalianPreview']);
Route::post('/pengembalian', [GudangController::class, 'pengembalianStore']);

Route::get('/proses', [GudangController::class, 'prosesIndex'])->name('proses');

Route::get('/kategori', [GudangController::class, 'kategoriIndex'])->name('kategori');
Route::post('/kategori', [GudangController::class, 'kategoriStore']);
Route::put('/kategori/{id}', [GudangController::class, 'kategoriUpdate']);
Route::delete('/kategori/{id}', [GudangController::class, 'kategoriDestroy']);

Route::get('/riwayat', [GudangController::class, 'riwayat'])->name('riwayat');
Route::get('/riwayat/pdf', [GudangController::class, 'riwayatPdf']);
Route::get('/riwayat/{id}', [GudangController::class, 'riwayatDetail'])->whereNumber('id');
Route::get('/proses', [GudangController::class, 'prosesIndex']);
Route::get('/proses/{id}', [GudangController::class, 'prosesDetail'])->whereNumber('id');
Route::get('/log', [GudangController::class, 'log'])->name('log');

Route::get('/qr', [GudangController::class, 'qrPage'])->name('qr');

Route::get('/b/{kode}', [GudangController::class, 'scan'])->name('scan');