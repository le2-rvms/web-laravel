<?php

namespace App\Http\Controllers\Admin\_;

use App\Enum\Admin\AUserType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Builder;
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
            $builder->where('a_user_type', '!=', AUserType::COMMON);
        });

        return $this->response()->withData($options)->respond();
    }

    public function update(Request $request, Admin $mock): Response
    {
        $admin = $this->resolveMockAdmin($mock);
        $token = Str::random(32);

        Cache::set("temporary_admin:{$token}", $admin->toArray(), 3600 * 4);

        return $this->response()->withData([
            'admin'       => $admin,
            'token'       => $token,
            'roles'       => $admin->roles()->pluck('name'),
            'permissions' => $admin->getAllPermissions()->pluck('name'),
        ])->respond();
    }

    public function destroy(string $id) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }

    private function resolveMockAdmin(Admin $admin): Admin
    {
        if (AUserType::COMMON === $admin->a_user_type->value) {
            throw ValidationException::withMessages([
                'mock' => ['该用户不能被体验'],
            ]);
        }

        $admin->forceFill([
            '_is_mock' => true,
        ]);

        return $admin;
    }
}
