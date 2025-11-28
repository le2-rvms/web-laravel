<?php

namespace App\Enum\Vehicle;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class ViVehicleDamageStatus extends EnumLikeBase
{
    public const int NO_DAMAGE = 0;
    public const int DAMAGED   = 1;

    public const array LABELS = [
        self::NO_DAMAGE => '无车损',
        self::DAMAGED   => '有车损',
    ];

    public const array colors = [
        self::NO_DAMAGE => Color::SUCCESS,
        self::DAMAGED   => Color::ERROR,
    ];
}
