<?php

namespace App\Providers;

use App\Models\_\PersonalAccessToken;
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
        foreach (glob(base_path().'/helpers/*.php') as $filename) {
            require_once $filename;
        }

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
