<?php

use App\Http\Controllers\BatchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealerController;
use App\Http\Controllers\PackingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ScheduleController;
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
Route::post('/riwayat/{upload}/jadwal', [BatchController::class, 'schedule'])->name('batches.schedule');

// Penjadwalan: jadwal kapal (manual) dan pengiriman harian
Route::get('/penjadwalan', [ScheduleController::class, 'index'])->name('schedule.index');
Route::get('/penjadwalan/baru', [ScheduleController::class, 'create'])->name('schedule.create');
Route::post('/penjadwalan', [ScheduleController::class, 'store'])->name('schedule.store');
Route::get('/penjadwalan/{shipment}', [ScheduleController::class, 'show'])->name('schedule.show');
Route::get('/penjadwalan/{shipment}/ubah', [ScheduleController::class, 'edit'])->name('schedule.edit');
Route::put('/penjadwalan/{shipment}', [ScheduleController::class, 'update'])->name('schedule.update');
Route::delete('/penjadwalan/{shipment}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
Route::post('/penjadwalan/{shipment}/kloter', [ScheduleController::class, 'attach'])->name('schedule.attach');
Route::delete('/penjadwalan/{shipment}/kloter/{upload}', [ScheduleController::class, 'detach'])->name('schedule.detach');

// Dealer dan MOQ
Route::get('/dealer', [DealerController::class, 'index'])->name('dealers.index');
Route::post('/dealer/moq', [DealerController::class, 'updateMoq'])->name('dealers.moq');

// Data referensi
Route::get('/master-barang', [ProductController::class, 'index'])->name('products.index');
Route::post('/master-barang/impor', [ProductController::class, 'import'])->name('products.import');
Route::get('/referensi', [ReferenceController::class, 'index'])->name('reference.index');
