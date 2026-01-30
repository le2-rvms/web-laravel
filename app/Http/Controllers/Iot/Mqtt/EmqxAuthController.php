<?php

namespace App\Http\Controllers\Iot\Mqtt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mqtt\EmqxAuthRequest;
use App\Models\Iot\IotMqttAccount;
use Illuminate\Http\JsonResponse;

class EmqxAuthController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function authenticate(EmqxAuthRequest $request): JsonResponse
    {
        $input = $request->validated();

        // 查找设备并校验口令（password + salt 的 sha256）。
        $mqttAccount = IotMqttAccount::query()->where(['user_name' => $input['username']])->first();

        if ($mqttAccount && hash('sha256', $input['password'].$mqttAccount->salt) === $mqttAccount->password_hash) {
            return $this->respond(
                'allow',
                false,
                ['client_id' => null, 'user_id' => $mqttAccount->act_id]
            );
        }

        return $this->respond('deny');
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }

    private function respond(string $result, bool $is_superuser = false, array $client_attrs = []): JsonResponse
    {
        // EMQX 期望返回 allow/deny 结果与 is_superuser 标记。
        $response = [
            'result'       => $result,
            'is_superuser' => $is_superuser ? 'true' : 'false',
            // 'client_attrs' => $client_attrs,
        ];

        return response()->json($response, 200);
    }
}
