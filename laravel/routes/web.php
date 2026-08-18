<?php

use App\Http\Controllers\BenchController;
use Illuminate\Support\Facades\Route;

Route::withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
])->group(function () {
    Route::get('/json', [BenchController::class, 'json']);
    Route::get('/plaintext', [BenchController::class, 'plaintext']);
    Route::get('/db', [BenchController::class, 'db']);
    Route::get('/queries', [BenchController::class, 'queries']);
    Route::get('/updates', [BenchController::class, 'updates']);
    Route::get('/fortunes', [BenchController::class, 'fortunes']);
});
