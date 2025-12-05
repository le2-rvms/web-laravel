<?php

namespace App\Http\Controllers\Admin\_;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\Admin\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    /**
     * 发送验证码
     */
    public function store(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => ['required', 'email', Rule::exists(Admin::class, 'email')],
            ]
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$cacheKey, &$cacheIpKey) {
            if (!$validator->failed()) {
                // 客户端ip限制
                $ip = $request->ip();

                $cacheIpKey = 'password_reset_ip:'.$ip;
                $attempts   = Cache::get($cacheIpKey, 0);

                if ($attempts > 3) {
                    $validator->errors()->add('email', '你已操作超过限制次数，请联系客服。');

                    return;
                }

                // 限制该邮箱每分钟只能请求一次验证码
                $cacheKey = 'password_reset_code:'.$request->input('email');
                if (Cache::has($cacheKey)) {
                    $validator->errors()->add('email', '请求过于频繁，请稍后再试');

                    return;
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        // 生成并缓存验证码
        $code = mt_rand(1000, 9999);
        Cache::put($cacheKey, $code, 1 * 60);

        Cache::has($cacheIpKey) ? Cache::increment($cacheIpKey) : Cache::put($cacheIpKey, 1);

        // 发送验证码
        Mail::to($input['email'])->send(new PasswordResetCodeMail($code));

        $this->response()->withMessages('验证码已发送，请检查您的邮箱');

        return $this->response()->respond();
    }

    /**
     * 重置密码
     */
    public function update(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email'                 => ['required', 'email', Rule::exists(Admin::class, 'email')],
                'code'                  => ['required', 'numeric'],
                'password'              => ['required', 'min:8', 'confirmed'],
                'password_confirmation' => ['required', 'min:8'],
            ]
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$cacheKey) {
            if (!$validator->failed()) {
                $cacheKey = 'password_reset_code_'.$request->input('email');
                if (Cache::get($cacheKey) !== $request->input('code')) {
                    $validator->errors()->add('code', '验证码无效或已过期');
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        // 更新用户密码
        Admin::query()->where('email', $input['email'])->update([
            'password'             => Hash::make($input['password']),
            'password_verified_at' => now(),
        ]);

        // 删除验证码缓存
        Cache::forget($cacheKey);

        $this->response()->withMessages('密码已重置');

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
