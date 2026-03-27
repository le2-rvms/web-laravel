<?php

use App\Http\Controllers\Admin\_\ReverbWsDemoController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//    return redirect()->route('home');
// });

// Auth::routes(['register' => false]);
// Auth::routes();

Route::get('/reverb/ws-demo-token', [ReverbWsDemoController::class, 'token']);
Route::get('/reverb/vue-demo', [ReverbWsDemoController::class, 'vueDemo']);
