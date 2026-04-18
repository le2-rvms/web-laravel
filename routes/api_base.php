<?php

use App\Http\Controllers\Base\CompanyController;
use App\Http\Controllers\Base\InitController;
use Illuminate\Support\Facades\Route;

Route::prefix('no-auth')->group(function () {
    Route::get('/init', [InitController::class, 'index']);

    Route::resource('company', CompanyController::class)->only(['store', 'show']);
});
