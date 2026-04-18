<?php

namespace App\Http\Controllers\WebAdmin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppBootstrapController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Models\Admin\Admin $admin */
        $admin = $request->user();

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'user_type' => $admin->a_user_type?->value ?? $admin->a_user_type,
            ],
            'roles' => $admin->getRoleNames()->values(),
            'permissions' => $admin->getAllPermissions()->pluck('name')->values(),
            'app' => [
                'name' => config('app.name'),
                'env' => app()->environment(),
                'url' => config('app.url'),
                'locale' => app()->getLocale(),
            ],
            'nav' => [
                [
                    'label' => '仪表盘',
                    'href' => '/web-admin',
                    'icon' => 'layout-dashboard',
                ],
            ],
        ]);
    }
}
