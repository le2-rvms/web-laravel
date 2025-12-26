<?php

namespace App\Http\Controllers\Base;

use App\Exceptions\ServerException;
use App\Http\Controllers\Controller;
use App\Mail\CompanyRegistration;
use Dotenv\Dotenv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CompanyController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function show($company, Request $request): Response
    {
        $input = Validator::make(
            ['company' => $company],
            ['company' => ['required', 'regex:/^[\w-]+$/']],
        )->after(function ($validator) use ($company, &$envFilePath) {
            $envFilePath = join(DIRECTORY_SEPARATOR, [
                base_path('env'),
                $company.'.env',
            ]);

            if (!file_exists($envFilePath)) {
                $validator->errors()->add('filename', '公司输入有误');
            }
        })
            ->validate()
        ;

        $rawContent = file_get_contents($envFilePath);

        $vars = Dotenv::parse($rawContent);

        $result = array_intersect_key($vars, array_flip(['COMPANY_ID', 'MOCK_ENABLE', 'COMPANY__HOST_ID', 'COMPANY__HOST_DOMAIN_BASE']));

        return $this->response()->withData($result)->respond();
    }

    public function store(Request $request)
    {
        $input = Validator::make($request->all(), [
            'company_name'    => ['required', 'string', 'max:100'],
            'cu_contact_name' => ['required', 'string', 'max:10'],
            'contact_phone'   => ['required', 'digits:11'],
        ], [
            'company_name.required'    => '请填写公司名称',
            'company_name.max'         => '公司名称不能超过100个字符',
            'cu_contact_name.required' => '请填写联系人',
            'contact_phone.required'   => '请填写手机号',
            'contact_phone.digits'     => '手机号必须是11位数字',
        ])->after(function ($validator) use ($request, &$cacheIpKey) {
            if ($validator->failed()) {
                return;
            }
            // 客户端ip限制
            $ip = $request->ip();

            $cacheIpKey = 'register_attempts_ip:'.$ip;
            $attempts   = Cache::get($cacheIpKey, 0);

            if ($attempts > 3) {
                $validator->errors()->add('company_name', '超过注册次数限制，请联系客服。');

                return;
            }
        })
            ->validate()
        ;

        try {
            Mail::to(config('mail.from.address'))
                ->send(new CompanyRegistration($input))
            ;
        } catch (\Exception $e) {
            throw new ServerException('发送公司注册邮件失败，请联系客服。', 0, $e);
        }

        $this->response()->withMessages('已发送，请等待审核，也可联系客服。');

        Cache::has($cacheIpKey) ? Cache::increment($cacheIpKey) : Cache::put($cacheIpKey, 1);

        return $this->response()->respond();
    }

    // 其余 Resource 方法保持抛 404
    public function index()
    {
        abort(404);
    }

    public function create()
    {
        abort(404);
    }

    public function edit($id)
    {
        abort(404);
    }

    public function update(Request $request, $id)
    {
        abort(404);
    }

    public function destroy($id)
    {
        abort(404);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
