<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\Admin\Admin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(function () {
            return Inertia::render('Admin/Auth/Login', [
                'status' => session('status'),
                'canResetPassword' => true,
            ]);
        });

        Fortify::requestPasswordResetLinkView(function () {
            return Inertia::render('Admin/Auth/ForgotPassword', [
                'status' => session('status'),
            ]);
        });

        Fortify::resetPasswordView(function (Request $request) {
            return Inertia::render('Admin/Auth/ResetPassword', [
                'email' => $request->string('email')->toString(),
                'token' => $request->route('token'),
                'status' => session('status'),
            ]);
        });

        Fortify::authenticateUsing(function (Request $request) {
            $admin = Admin::query()
                ->where('email', $request->string('email')->toString())
                ->first();

            if (!$admin || !Hash::check($request->string('password')->toString(), $admin->password)) {
                return null;
            }

            if ($admin->a_expires_at && $admin->a_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    Fortify::username() => ['临时账户已过期，无法登录。'],
                ]);
            }

            return $admin;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
