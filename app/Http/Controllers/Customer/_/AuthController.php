<?php

namespace App\Http\Controllers\Customer\_;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function sendVerificationCode(Request $request, SmsService $smsService): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'phone' => ['required', 'digits:11', Rule::exists(Customer::class, 'cu_contact_phone')],
            ]
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$cacheKey, &$cacheIpKey) {
            if ($validator->failed()) {
                return;
            }
            // 客户端ip限制
            $ip = $request->ip();

            $cacheIpKey = 'customer_verification_ip:'.$ip;
            $attempts   = Cache::get($cacheIpKey, 0);

            if ($attempts > 3) {
                $validator->errors()->add('email', '你已操作超过限制次数，请联系客服。');

                return;
            }

            // 限制每分钟只能请求一次验证码
            $cacheKey = 'customer_verification_code:'.$request->input('phone');
            if (Cache::has($cacheKey)) {
                $validator->errors()->add('email', '请求过于频繁，请稍后再试');

                return;
            }
        })
            ->validate()
        ;

        $code = mt_rand(1000, 9999);

        Cache::put($cacheKey, $code, 1 * 60);
        Cache::has($cacheIpKey) ? Cache::increment($cacheIpKey) : Cache::put($cacheIpKey, 1);

        // 发送验证码短信，使用短信服务商的 API 进行发送
        $smsService->verificationCode($input['phone'], $code);

        return $this->response()->withMessages('验证码已发送')->respond();
    }

    public function login(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'phone' => ['required', 'digits:11', Rule::exists(Customer::class, 'cu_contact_phone')],
                'code'  => ['required', 'digits:4'],
            ]
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$cacheKey) {
            if ($validator->failed()) {
                return;
            }
            $cacheKey = 'customer_verification_code:'.$request->input('phone');

            $cachedCode = Cache::get($cacheKey);

            if ($cachedCode != $request->input('code')) {
                $validator->errors()->add('code', '验证码不正确');

                return;
            }
        })
            ->validate()
        ;

        $customer = Customer::query()->where('cu_contact_phone', $input['phone'])->first();

        DB::transaction(function () use ($customer, &$token, &$cacheKey) {
            $customer->tokens()->delete();

            $token = $customer->createToken('rc')->plainTextToken;

            Cache::forget($cacheKey);
        });

        return $this->response()->withData([
            'token'    => $token,
            'customer' => $customer,
        ])->respond();
    }

    public function getUserInfo(Request $request): Response
    {
        return $this->response()->withData($request->user())->respond();
    }

    public function mock(Request $request): Response
    {
        /** @var Customer $customer */
        $customer = Customer::query()->whereLike('cu_contact_name', '演示%')->inRandomOrder()->firstOrFail();

        $token = Str::random(32);

        Cache::set("temporary_customer:{$token}", $customer->cu_id, 3600 * 3);

        return $this->response()->withMessages('测试登录成功')->withData([
            'customer' => $customer,
            'token'    => $token,
        ])->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
