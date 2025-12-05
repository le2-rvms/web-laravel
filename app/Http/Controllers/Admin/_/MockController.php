<?php

namespace App\Http\Controllers\Admin\_;

use App\Enum\Admin\AdmUserType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MockController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(): JsonResponse
    {
        $options = Admin::options(function (Builder $builder) {
            $builder->where('user_type', '!=', AdmUserType::COMMON);
        });

        return $this->response()->withData($options)->respond();
    }

    public function update(Request $request, Admin $mock): Response
    {
        if (AdmUserType::COMMON === $mock->user_type) {
            throw ValidationException::withMessages([
                'mock' => ['该用户不能被体验'],
            ]);
        }
        $admin = $mock;
        $admin->forceFill([
            'is_mock' => true,
        ]);
        $token = Str::random(32);

        Cache::set("temporary_admin:{$token}", $admin->toArray(), 3600 * 4);

        $roleNames = $admin->roles()->pluck('name');

        $permissionNames = $admin->getAllPermissions()->pluck('name');

        return $this->response()->withData([
            'admin'       => $admin,
            'token'       => $token,
            'roles'       => $roleNames,
            'permissions' => $permissionNames,
        ])->respond();
    }

    public function destroy(string $id) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
