<?php

namespace App\Enum\Delivery;

use App\Enum\EnumLikeBase;

class DlSendStatus extends EnumLikeBase
{
    public const int ST_PENDING    = 0;
    public const int ST_SENDING    = 1;
    public const int ST_SENT       = 2;
    public const int ST_DELIVERED  = 3;
    public const int ST_FAILED     = 4;
    public const int ST_CANCELED   = 5;
    public const int ST_SUPPRESSED = 6;

    public const array LABELS = [
        self::ST_PENDING    => '未发送',
        self::ST_SENDING    => '发送中',
        self::ST_SENT       => 'ST_SENT',
        self::ST_DELIVERED  => '已发送',
        self::ST_FAILED     => '发送失败',
        self::ST_CANCELED   => '取消发送',
        self::ST_SUPPRESSED => 'ST_SUPPRESSED',
    ];
}
