<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Cek kapasitas (unggah kloter baru)
Route::get('/packing', [PackingController::class, 'index'])->name('packing.index');
Route::post('/packing/optimize', [PackingController::class, 'optimize'])->name('packing.optimize');
Route::post('/upload', [UploadController::class, 'upload'])->name('upload');
Route::get('/result/{id}', [UploadController::class, 'result'])->name('result');

// Riwayat kloter (satu unggahan = satu kloter)
Route::get('/riwayat', [BatchController::class, 'index'])->name('batches.index');
Route::get('/riwayat/{upload}', [BatchController::class, 'show'])->name('batches.show');
Route::delete('/riwayat/{upload}', [BatchController::class, 'destroy'])->name('batches.destroy');

// Data referensi
Route::get('/master-barang', [ProductController::class, 'index'])->name('products.index');
Route::post('/master-barang/impor', [ProductController::class, 'import'])->name('products.import');
Route::get('/referensi', [ReferenceController::class, 'index'])->name('reference.index');
