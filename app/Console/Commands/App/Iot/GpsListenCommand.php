<?php

namespace App\Console\Commands\App\Iot;

use App\Events\GpsPositionUpdated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: 'app:iot:gps-listen',
    description: 'Listen PostgreSQL NOTIFY for device_last_position_{COMPANY_ID} and broadcast updates'
)]
class GpsListenCommand extends Command
{
    private bool $shouldStop = false;

    public function handle(): int
    {
        $companyId = config('app.company_id');

        if (!$companyId) {
            $this->error('请设置 COMPANY_ID 环境变量以确定监听通道。');

            return CommandAlias::FAILURE;
        }

        $this->registerSignalHandlers();

        $channel        = 'device_last_position_'.$companyId;
        $backoffSeconds = 1;

        // 指数退避重连，避免短时故障造成连接风暴。
        while (!$this->shouldStop) {
            try {
                $pdo = $this->connectAndListen($channel);
                $this->info('Listening on channel: '.$channel);
                $backoffSeconds  = 1;
                $lastHealthCheck = time();

                while (!$this->shouldStop) {
                    $notification = $pdo->pgsqlGetNotify(\PDO::FETCH_ASSOC, 30000);

                    if (false === $notification) {
                        if (time() - $lastHealthCheck >= 60) {
                            $pdo->query('SELECT 1');
                            $lastHealthCheck = time();
                        }

                        continue;
                    }

                    $lastHealthCheck = time();
                    $this->handlePayload($notification['payload'] ?? null);
                }
            } catch (\Throwable $exception) {
                Log::error('GPS LISTEN 异常，准备重连', ['exception' => $exception]);

                if ($this->shouldStop) {
                    break;
                }

                // 指数退避，避免数据库或 Reverb 在异常时被频繁重连打爆。
                $this->warn('连接异常，'.$backoffSeconds.' 秒后重试...');
                sleep($backoffSeconds);
                $backoffSeconds = min($backoffSeconds * 2, 30);
            }
        }

        return CommandAlias::SUCCESS;
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        // 长连接需要可控退出，避免异常终止造成状态不一致。
        pcntl_async_signals(true);

        pcntl_signal(\SIGTERM, function (): void {
            $this->shouldStop = true;
        });

        pcntl_signal(\SIGINT, function (): void {
            $this->shouldStop = true;
        });
    }

    private function connectAndListen(string $channel): \PDO
    {
        // 断线重连时清理旧连接，避免复用失效句柄。
        DB::purge('timescaledb');

        $connection = DB::connection('timescaledb');
        $pdo        = $connection->getPdo();

        $escaped = '"'.str_replace('"', '""', $channel).'"';
        $pdo->exec('LISTEN '.$escaped);

        return $pdo;
    }

    private function handlePayload(?string $rawPayload): void
    {
        if (null === $rawPayload || '' === $rawPayload) {
            Log::warning('GPS NOTIFY payload 为空');

            return;
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            Log::warning('GPS NOTIFY payload 无法解析', ['payload' => $rawPayload]);

            return;
        }

        if (!isset($payload['terminal_id'], $payload['gps_time'], $payload['latitude_gcj'], $payload['longitude_gcj'])) {
            Log::warning('GPS NOTIFY payload 缺少必要字段', ['payload' => $payload]);

            return;
        }

        try {
            // 广播失败不影响监听主循环，避免单条数据导致服务中断。
            broadcast(new GpsPositionUpdated(
                payload: $payload,
                source: 'pgsql_listen'
            ));
        } catch (\Throwable $exception) {
            Log::error('GPS 广播失败', [
                'payload'   => $payload,
                'exception' => $exception,
            ]);
        }
    }
}
