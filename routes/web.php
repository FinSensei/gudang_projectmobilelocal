<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GudangController;

Route::get('/', [GudangController::class, 'index']);

Route::get('/barang', [GudangController::class, 'barangForm'])->name('barang');
Route::post('/barang', [GudangController::class, 'barangStore']);

Route::get('/restock', [GudangController::class, 'restockForm'])->name('restock');
Route::post('/restock', [GudangController::class, 'restockStore']);

Route::post('/rusak', [GudangController::class, 'rusakStore']);

Route::get('/acara', [GudangController::class, 'acaraForm'])->name('acara');
Route::post('/acara', [GudangController::class, 'acaraStore']);

Route::get('/pengembalian', [GudangController::class, 'pengembalianForm'])->name('pengembalian');
Route::post('/pengembalian/preview', [GudangController::class, 'pengembalianPreview']);
Route::post('/pengembalian', [GudangController::class, 'pengembalianStore']);

Route::get('/qr', [GudangController::class, 'qrPage'])->name('qr');

Route::get('/b/{kode}', [GudangController::class, 'scan'])->name('scan');