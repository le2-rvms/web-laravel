<?php

namespace App\Enum\VehicleManualViolation;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class VvStatus extends EnumLikeBase
{
    public const int UNPROCESSED = 0;

    public const int PROCESSED = 1;

    public const array LABELS = [
        self::UNPROCESSED => '未处理',
        self::PROCESSED   => '已处理',
    ];

    public const array colors = [
        self::UNPROCESSED => Color::ERROR,
    ];
}
