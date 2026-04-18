<?php

namespace App\Http\Middleware;

use App\Models\Customer\Customer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TemporaryCustomer
{
    public function handle(Request $request, \Closure $next)
    {
        $authMiddleware = app(Authenticate::class, ['guards' => ['sanctum']]);

        try {
            // 尝试走 auth:sanctum 的认证逻辑
            return $authMiddleware->handle($request, function ($request) use ($next) {
                return $next($request);
            }, 'sanctum');
        } catch (AuthenticationException $exception) {
            // 如果 Sanctum 认证失败，则尝试额外的临时用户验证
            if ($this->additionalVerification($request)) {
                return $next($request);
            }

            throw $exception;
        }
    }

    protected function additionalVerification(Request $request): bool
    {
        $token = $request->bearerToken();

        if (!$token) {
            return false;
        }

        $cu_id = Cache::get("temporary_customer:{$token}");

        if (!$cu_id) {
            return false;
        }

        /** @var Customer $tempUser */
        $tempUser = Customer::query()->find($cu_id);
        if (!$tempUser) {
            Cache::forget("temporary_customer:{$token}");

            return false;
        }

        // 覆盖当前认证用户
        Auth::setUser($tempUser);

        return true;
    }
}
