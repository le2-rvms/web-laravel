<?php

use App\Http\Controllers\WebAdmin\AppBootstrapController;
use App\Http\Controllers\WebAdmin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('web-admin.dashboard');
    Route::get('/app/bootstrap', AppBootstrapController::class)->name('web-admin.app.bootstrap');
});
