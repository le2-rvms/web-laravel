<?php

namespace App\Enum\Vehicle;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class VmvStatus extends EnumLikeBase
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
