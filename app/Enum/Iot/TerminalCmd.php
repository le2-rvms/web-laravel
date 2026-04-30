<?php

namespace App\Enum\Iot;

use App\Enum\KvTrait;

class TerminalCmd
{
    use KvTrait;

    public const array kv = [
        'lock' => [
            'key'     => 'lock',
            'label'   => '上锁',
            'kind'    => 'single-click',
            'channel' => 1,
            'params'  => [
                'timeout_s' => 120,
            ],
            'buttonType' => 'warn',
            'icon'       => 'icon-jiesuo',
        ],
        'unlock' => [
            'key'     => 'unlock',
            'label'   => '解锁',
            'kind'    => 'single-click',
            'channel' => 2,
            'params'  => [
                'timeout_s' => 120,
            ],
            'buttonType' => 'warn',
            'icon'       => 'icon-suoding',
        ],
        'beep' => [
            'key'     => 'beep',
            'label'   => '蜂鸣',
            'kind'    => 'single-click',
            'channel' => 3,
            'params'  => [
                'timeout_s' => 120,
            ],
            'buttonType' => 'warn',
            'icon'       => 'icon-laba',
        ],
        'foo' => [
            'key'     => 'foo',
            'label'   => '后备箱',
            'kind'    => 'double-click',
            'channel' => 1,
            'params'  => [
                'on1' => 120,
                'off' => 80,
                'on2' => 120,
            ],
            'buttonType' => 'warn',
            'icon'       => 'icon-houbeixiang',
        ],
    ];
}
