<?php

namespace App\Providers;

use App\Models\_\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //        $tz = config('app.timezone') ?: date_default_timezone_get();
        //        date_default_timezone_set($tz);

        foreach (glob(base_path().'/helpers/*.php') as $filename) {
            require_once $filename;
        }

        RateLimiter::for('emqx-auth', function (Request $request) {
            $ip       = (string) $request->ip();
            $username = (string) $request->input('username', '');

            return Limit::perMinute(60)->by($ip.'|'.$username);
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
