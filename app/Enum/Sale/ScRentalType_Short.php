<?php

namespace App\Enum\Sale;

class ScRentalType_Short extends ScRentalType
{
    public const array LABELS = [
        self::LONG_TERM  => '长租',
        self::SHORT_TERM => '短租',
    ];
}
