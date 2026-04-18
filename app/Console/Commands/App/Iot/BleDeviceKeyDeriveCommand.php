<?php

namespace App\Console\Commands\App\Iot;

use App\Services\BleKeyDeriver;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: 'app:iot:ble:k-dev-enc',
    description: '根据 terminal_no 派生 16 字节 AES 密钥'
)]
class BleDeviceKeyDeriveCommand extends Command
{
    protected $signature = 'app:iot:ble:k-dev-enc
        {terminal_no : 10 位十进制设备编号字符串}';

    public function handle(): int
    {
        $terminalNo = (string) $this->argument('terminal_no');

        try {
            $result = BleKeyDeriver::deriveByTerminalNo($terminalNo);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return CommandAlias::FAILURE;
        }

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return CommandAlias::SUCCESS;
    }
}
