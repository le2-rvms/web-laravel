<?php

namespace App\Enum\VehicleRepair;

use App\Enum\EnumLikeBase;

class VrConfirmStatus_ extends EnumLikeBase
{
    public const int UNCONFIRMED = 0;
    public const int CONFIRMED   = 1;

    public const array LABELS = [
        self::UNCONFIRMED => '未确认',
        self::CONFIRMED   => '已确认',
    ];

    public const init  = self::UNCONFIRMED;
    public const final = self::CONFIRMED;
}
