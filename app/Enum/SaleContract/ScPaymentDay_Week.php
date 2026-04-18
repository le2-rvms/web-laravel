<?php

namespace App\Enum\SaleContract;

use App\Enum\EnumLikeBase;

class ScPaymentDay_Week extends EnumLikeBase
{
    public const int MONDAY    = 1;
    public const int TUESDAY   = 2;
    public const int WEDNESDAY = 3;
    public const int THURSDAY  = 4;
    public const int FRIDAY    = 5;
    public const int SATURDAY  = 6;
    public const int SUNDAY    = 7;

    public const array LABELS = [
        self::MONDAY    => '周一',
        self::TUESDAY   => '周二',
        self::WEDNESDAY => '周三',
        self::THURSDAY  => '周四',
        self::FRIDAY    => '周五',
        self::SATURDAY  => '周六',
        self::SUNDAY    => '周日',
    ];
}
