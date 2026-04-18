<?php

namespace App\Enum\Booking;

use App\Enum\EnumLikeBase;

class BoOrderStatus extends EnumLikeBase
{
    public const string UNPROCESSED = 'unprocessed';
    public const string PROCESSED   = 'processed';
    public const string CANCELLED   = 'cancelled';

    public const array LABELS = [
        self::UNPROCESSED => '未处理',
        self::PROCESSED   => '已处理',
        self::CANCELLED   => '已取消',
    ];
}
