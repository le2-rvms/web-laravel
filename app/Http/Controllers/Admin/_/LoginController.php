<?php

namespace App\Http\Controllers\Admin\_;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use Carbon\Carbon;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

enum AdminLoginClientEnum: string
{
    case MP = 'mp';

    case H5 = 'h5';
}

class LoginController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function login(Request $request)
    {
        $input = $request->validate([
            $this->username() => ['required', 'string'],
            'password'        => ['required', 'string'],
            'client'          => ['required', Rule::enum(AdminLoginClientEnum::class)],
        ]);

        if (method_exists($this, 'hasTooManyLoginAttempts')
            && $this->hasTooManyLoginAttempts($request)) {
            // 触发频控锁定。
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            $this->clearLoginAttempts($request);

            /** @var Admin $admin */
            $admin = $this->guard()->user();

            if (method_exists($this, 'authenticated') && $response = $this->authenticated($request, $admin)) {
                return $response;
            }

            if ($admin->a_expires_at && $admin->a_expires_at->lt(Carbon::now())) {
                throw ValidationException::withMessages([
                    $this->username() => ['临时账户已过期，无法登录。'],
                ]);
            }

            if ($request->wantsJson()) {
                // 同一客户端只保留一个 token。
                // 删除用户的其他 token
                $admin->tokens()->where('name', '=', $input['client'])->delete();

                $expiresAt = match ($input['client']) {
                    // 不同客户端设置不同过期策略。
                    AdminLoginClientEnum::H5->value => Carbon::now()->addHours(12),
                    AdminLoginClientEnum::MP->value => Carbon::now()->addYears(10),
                };

                $token = $admin->createToken($input['client'], ['*'], $expiresAt)->plainTextToken;

                // 获取用户角色和权限
                $roles       = $admin->getRoleNames();
                $permissions = $admin->getAllPermissions()->pluck('name');

                $this->response()->withMessages('登录成功');

                return $this->response()->withData(compact('admin', 'token', 'roles', 'permissions'))->respond();
            }

            return redirect()->intended($this->redirectPath());
        }

        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }

    protected function username(): string
    {
        return 'email';
    }

    protected function guard(): StatefulGuard
    {
        return Auth::guard();
    }

    protected function redirectPath(): string
    {
        return '/';
    }

    protected function attemptLogin(Request $request): bool
    {
        return $this->guard()->attempt(
            $this->credentials($request),
            $request->boolean('remember')
        );
    }

    protected function credentials(Request $request): array
    {
        return $request->only($this->username(), 'password');
    }

    protected function hasTooManyLoginAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts());
    }

    protected function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());
    }

    protected function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    protected function fireLockoutEvent(Request $request): void
    {
        event(new Lockout($request));
    }

    protected function sendLockoutResponse(Request $request): never
    {
        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ])],
        ]);
    }

    protected function sendFailedLoginResponse(Request $request): never
    {
        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input($this->username())).'|'.$request->ip());
    }

    protected function maxAttempts(): int
    {
        return 5;
    }

    protected function decaySeconds(): int
    {
        return 60;
    }
}
