<?php

namespace App\Services;

use App\Enum\AuthUserType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpMqtt\Client\Facades\MQTT;

class IotTerminalCommandPublisher
{
    public function publish(string $terminalId, array $cmd): array
    {
        $commandId = $this->generateCommandId();

        $payload = [
            'event'       => 'cmd',
            'terminal_id' => $terminalId,
            'ts'          => now()->timestamp,
            'cmd_label'   => $cmd['label'],
            'payload'     => [
                'command_id' => $commandId,
                'action'     => $cmd['kind'],
                'params'     => $cmd['params'] + ['ch' => $cmd['channel']],
                'expire_ts'  => now()->addMinutes(5)->timestamp,
                'auth_user'  => AuthUserType::getValue(),
            ],
        ];

        $topic = sprintf('v1/d/%s/down', $terminalId);

        try {
            $message = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            $mqtt = MQTT::connection();
            $mqtt->publish($topic, $message, 2);
            $mqtt->loop(true, true, 3);
        } catch (\Throwable $throwable) {
            throw ValidationException::withMessages([
                'mqtt' => 'MQTT 发布失败：'.$throwable->getMessage(),
            ]);
        } finally {
            try {
                MQTT::disconnect();
            } catch (\Throwable) {
            }
        }

        return [
            'topic'   => $topic,
            'payload' => $payload,
        ];
    }

    private function generateCommandId(): string
    {
        return 'cmd-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(4));
    }
}
