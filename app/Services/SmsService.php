<?php

namespace App\Services;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function verificationCode(string $phone, int $code): bool
    {
        $client         = app(Dysmsapi::class);
        $sendSmsRequest = new SendSmsRequest([
            'signName'      => '成都雷耳兔科技',
            'phoneNumbers'  => $phone,
            'templateCode'  => 'SMS_325970998',
            'templateParam' => json_encode(['code' => $code]), // "{\"code\":\"7788\"}"
        ]);
        $runtime = new RuntimeOptions([]);

        Log::channel('aliyun')->info(json_encode($sendSmsRequest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $resp = $client->sendSmsWithOptions($sendSmsRequest, $runtime);
            Log::channel('aliyun')->info(Utils::toJSONString($resp));

            return true;
        } catch (\Exception $error) {
            if (!$error instanceof TeaError) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }

            Log::channel('aliyun')->error($error);
        }

        return false;
    }
}
