<?php

use App\Http\Controllers\Iot\Mqtt\EmqxAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/emqx/auth', [EmqxAuthController::class, 'authenticate'])->middleware('throttle:emqx-auth');
