<?php

use App\Http\Controllers\Customer\_\AuthController;
use App\Http\Controllers\Customer\Device\IotTerminalControlController;
use App\Http\Controllers\Customer\Sale\SaleContractController;
use App\Http\Middleware\TemporaryCustomer;
use Illuminate\Support\Facades\Route;

Route::prefix('no-auth')->group(callback: function () {
    Route::post('send-verification-code', [AuthController::class, 'sendVerificationCode']);
    Route::post('login', [AuthController::class, 'login']);

    if (config('setting.mock.enable')) {
        Route::put('mock', [AuthController::class, 'mock']);
    }
});

Route::group(['middleware' => [config('setting.mock.enable') ? TemporaryCustomer::class : 'auth:sanctum']], function () {
    Route::get('user', [AuthController::class, 'getUserInfo']);

    Route::post('iot-terminal-controls', [IotTerminalControlController::class, 'store']);

    Route::resource('sale-contracts', SaleContractController::class)->only(['index', 'show']);
});
