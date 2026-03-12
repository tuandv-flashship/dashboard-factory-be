<?php

use App\Containers\AppSection\Authentication\UI\API\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'versioned'])
    ->middleware(['throttle:' . config('appSection-authentication.throttle.welcome', '120,1')]);
