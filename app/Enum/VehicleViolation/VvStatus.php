<?php

namespace App\Enum\VehicleViolation;

use App\Enum\EnumLikeBase;

class VvStatus extends EnumLikeBase
{
    public const int PAID = 1;

    public const int NO_NEED_TO_PAY = 2;

    public const array LABELS = [
        self::PAID           => '已交款',
        self::NO_NEED_TO_PAY => '无需交款',
    ];
}
