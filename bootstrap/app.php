<?php

use App\Exceptions\ClientException;
use App\Http\Middleware\LogRequests;
use App\Providers\BroadcastServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        BroadcastServiceProvider::class,
    ])
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/status',
        then: function () {
            Route::middleware('web')
                ->prefix('web-admin')
                ->group(base_path('routes/web_admin.php'))
            ;

            Route::middleware('api')
                ->name('api-base.')
                ->prefix('api-base')
                ->group(base_path('routes/api_base.php'))
            ;

            Route::middleware('api')
                ->name('api-admin.')
                ->prefix('api-admin')
                ->group(base_path('routes/api_admin.php'))
            ;

            Route::middleware('api')
                ->name('api-customer.')
                ->prefix('api-customer')
                ->group(base_path('routes/api_customer.php'))
            ;

            Route::middleware('api')
                ->name('api-iot.')
                ->prefix('api-iot')
                ->group(base_path('routes/api_iot.php'))
            ;
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            LogRequests::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            ClientException::class,
        ]);
        //        $exceptions->report(function (RequestException $e) {
        //            return false;
        //        });
    })->create()
;
