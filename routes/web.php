<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/ingest', function () {
    return view('ingest');
})->name('ingest');

Route::get('/ask', function () {
    return view('ask');
})->name('ask');
