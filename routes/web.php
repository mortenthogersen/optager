<?php

use App\Http\Controllers\RecordingController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('record.create'));

Route::get('/record', [RecordingController::class, 'create'])->name('record.create');
Route::post('/record', [RecordingController::class, 'store'])->name('record.store');
Route::get('/record/{uuid}', [RecordingController::class, 'success'])->name('record.success');
