<?php

namespace App\Enum\Sale;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class SsReturnStatus extends EnumLikeBase
{
    public const int UNCONFIRMED = 0;

    public const int CONFIRMED = 1;

    public const array LABELS = [
        self::UNCONFIRMED => '未确认',
        self::CONFIRMED   => '已确认',
    ];

    public const array colors = [
        self::UNCONFIRMED => Color::SUCCESS,
        self::CONFIRMED   => Color::ERROR,
    ];
}
