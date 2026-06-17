<?php

use App\Http\Controllers\DevLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/app', function () {
    return view('app');
})->name('app');

Route::redirect('/', '/app');

if (app()->isLocal()) {
    Route::get('/dev-login', [DevLoginController::class, 'index']);
    Route::get('/dev-login/{telegramId}', [DevLoginController::class, 'show'])->whereNumber('telegramId');
}
