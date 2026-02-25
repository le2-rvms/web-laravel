<?php

namespace App\Enum\Iot;

use App\Enum\EnumLikeBase;

class EventType_CMD extends EnumLikeBase
{
    public const string CMD     = 'cmd';
    public const string CMD_ACK = 'cmd-ack';

    public const array LABELS = [
        self::CMD     => '命令下发',
        self::CMD_ACK => '命令执行',
    ];
}
