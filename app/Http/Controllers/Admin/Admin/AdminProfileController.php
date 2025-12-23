<?php

namespace App\Http\Controllers\Admin\Admin;

use App\Attributes\PermissionNoneType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAdminIsMock;
use App\Models\Admin\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

#[PermissionNoneType('个人密码')]
class AdminProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware(CheckAdminIsMock::class);
    }

    public function show()
    {
        $admin = Auth::user();

        return $this->response()->withData($admin)->respond();
    }

    public function edit(Request $request)
    {
        $admin = Auth::user();

        return $this->response()->withData($admin)->respond();
    }

    public function update(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'current_password'      => ['required', 'string', 'min:8', 'current_password'],
                'password'              => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
                'password_confirmation' => ['required', 'string', 'min:8'],
            ],
            [],
            trans_property(Admin::class)
        )
            ->validate()
        ;

        /** @var Admin $admin */
        $admin = $request->user();

        DB::transaction(function () use (&$input, &$admin) {
            $admin->fill($input);
            $admin->save();

            // 踢掉其它已登录会话（基于会话的身份验证时有用）
            //            Auth::logoutOtherDevices($input['password']);
        });

        $this->response()->withMessages(message_success(__METHOD__));

        return $this->response()->withRedirect(redirect()->route('home'))->respond();
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
