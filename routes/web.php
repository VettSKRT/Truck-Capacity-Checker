<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\PackingController;

Route::get('/', [PackingController::class, 'index']);
Route::post('/upload', [UploadController::class, 'upload'])->name('upload');
Route::get('/result/{id}', [UploadController::class, 'result'])->name('result');
Route::get('/packing', [PackingController::class, 'index'])->name('packing.index');
Route::post('/packing/optimize', [PackingController::class, 'optimize'])->name('packing.optimize');
