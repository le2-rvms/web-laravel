<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TemporaryAdmin
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

        $adminCached = Cache::get("temporary_admin:{$token}");

        if (!$adminCached || !isset($adminCached['id'])) {
            return false;
        }

        // 构造临时用户对象
        $tempAdmin = new Admin();
        $tempAdmin->forceFill($adminCached);

        //        $tempAdmin->exists = true;

        $roles = $tempAdmin->roles;

        if ($roles->isEmpty()) {
            return false;
        }

        $permissions = $tempAdmin->permissions;

        // 预加载角色和权限关联
        //        $tempAdmin->setRelation('roles', $roles);
        //        $tempAdmin->setRelation('permissions', $permissions);

        // 覆盖当前认证用户
        Auth::setUser($tempAdmin);

        return true;
    }
}
