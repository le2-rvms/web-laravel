<?php

namespace App\Enum\SaleContract;

use App\Enum\EnumLikeBase;

class ScRentalType extends EnumLikeBase
{
    public const string LONG_TERM  = 'long_term';
    public const string SHORT_TERM = 'short_term';

    public const array LABELS = [
        self::LONG_TERM  => '长租 - 分期付租金模式',
        self::SHORT_TERM => '短租 - 一次性付租金模式',
    ];
}
