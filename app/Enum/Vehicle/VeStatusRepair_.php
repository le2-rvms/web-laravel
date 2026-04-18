<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VeStatusRepair_ extends EnumLikeBase
{
    public const string NO_REPAIR = 'no_repair';

    public const string NEEDS_REPAIR = 'needs_repair';

    public const array LABELS = [
        self::NO_REPAIR    => '无需维修',
        self::NEEDS_REPAIR => '需要维修',
    ];
}
