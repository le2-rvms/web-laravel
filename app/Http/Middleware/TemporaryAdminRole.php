<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use App\Models\Admin\AdminPermission;
use App\Models\Admin\AdminRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TemporaryAdminRole
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

        $roleCached = Cache::get("temporary_admin:{$token}");

        if (!$roleCached || !isset($roleCached['id'])) {
            return false;
        }

        // 构造临时用户对象
        $tempUser = new Admin();
        $tempUser->forceFill([
            'is_mock' => true,
            'name'    => $roleCached['name'],
        ]);

        $tempUser->exists = true;

        $roles = AdminRole::query()->where('id', '=', $roleCached['id'])->get();

        if ($roles->isEmpty()) {
            return false;
        }

        $roleIds = $roles->pluck('id')->toArray();

        $permissions = AdminPermission::query()->whereHas('roles', function (Builder $query) use ($roleIds) {
            $query->whereIn('id', $roleIds);
        })->get();

        // 预加载角色和权限关联
        $tempUser->setRelation('roles', $roles);
        $tempUser->setRelation('permissions', $permissions);

        // 覆盖当前认证用户
        Auth::setUser($tempUser);

        return true;
    }
}
