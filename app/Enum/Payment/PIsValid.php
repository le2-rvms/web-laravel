<?php

namespace App\Enum\Payment;

use App\Enum\EnumLikeBase;

class PIsValid extends EnumLikeBase
{
    public const int VALID   = 1;
    public const int INVALID = 0;

    public const array LABELS = [
        self::VALID   => '有效',
        self::INVALID => '无效',
    ];
}
