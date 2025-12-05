<?php

namespace App\Http\Controllers\Admin\_;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

enum AdminLoginClientEnum: string
{
    case MP = 'mp';

    case H5 = 'h5';
}

class LoginController extends Controller
{
    use AuthenticatesUsers;

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
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            $this->clearLoginAttempts($request);

            /** @var Admin $admin */
            $admin = $this->guard()->user();

            if ($response = $this->authenticated($request, $admin)) {
                return $response;
            }

            if ($admin->expires_at && $admin->expires_at->lt(Carbon::now())) {
                throw ValidationException::withMessages([
                    $this->username() => ['临时账户已过期，无法登录。'],
                ]);
            }

            if ($request->wantsJson()) {
                // 删除用户的其他 token
                $admin->tokens()->where('name', '=', $input['client'])->delete();

                $expiresAt = match ($input['client']) {
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
}
