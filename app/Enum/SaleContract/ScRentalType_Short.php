<?php

namespace App\Enum\SaleContract;

class ScRentalType_Short extends ScRentalType
{
    public const array LABELS = [
        self::LONG_TERM  => '长租',
        self::SHORT_TERM => '短租',
    ];
}
