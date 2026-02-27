<?php

namespace App\Console\Commands\App\Iot;

use App\Services\BleKeyDeriver;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: 'app:iot:ble:k-dev-enc',
    description: '按 device_id 派生 BLE 离线命令密钥 K_dev_enc'
)]
class BleDeviceKeyDeriveCommand extends Command
{
    protected $signature = 'app:iot:ble:k-dev-enc
        {--device_id= : 设备ID（为空时生成通用密钥）}
       ';

    public function handle(): int
    {
        $deviceId = (string) ($this->option('device_id') ?? '');

        try {
            $normalizedDeviceId = BleKeyDeriver::normalizeDeviceId($deviceId);
            $kDevEncHex         = BleKeyDeriver::deriveKDevEncHex($deviceId);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return CommandAlias::FAILURE;
        }

        $this->table(
            ['device_id', 'normalized_device_id', 'k_dev_enc'],
            [[$deviceId, $normalizedDeviceId, $kDevEncHex]]
        );

        return CommandAlias::SUCCESS;
    }
}
