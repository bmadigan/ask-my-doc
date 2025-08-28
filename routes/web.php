<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/ingest', [DocumentController::class, 'ingest'])->name('ingest');
Route::get('/ask', [DocumentController::class, 'ask'])->name('ask');
