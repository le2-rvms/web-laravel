<?php

namespace App\Enum\Iot;

use App\Enum\EnumLikeBase;

class EventType_CONN extends EnumLikeBase
{
    public const string CONNECT    = 'connect';
    public const string DISCONNECT = 'disconnect';

    public const array LABELS = [
        self::CONNECT    => '连接',
        self::DISCONNECT => '断开',
    ];
}
