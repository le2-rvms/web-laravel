<?php

namespace App\Enum\Payment;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class PIsValid extends EnumLikeBase
{
    public const int VALID   = 1;
    public const int INVALID = 0;

    public const array LABELS = [
        self::VALID   => '有效',
        self::INVALID => '无效',
    ];

    public const array colors = [
        self::INVALID => Color::ERROR,
        self::VALID   => Color::SUCCESS,
    ];
}
