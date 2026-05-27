<?php

use App\Containers\AppSection\Authentication\UI\WEB\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class)
    ->name('login')
    ->middleware([
        'guest',
        'throttle:' . config('appSection-authentication.throttle.web_login', '10,1'),
    ]);

Route::get('login', [LoginController::class, 'showForm'])
    ->name('login.form')
    ->middleware(['guest']);
