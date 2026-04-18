<?php

namespace App\Enum\Booking;

use App\Enum\EnumLikeBase;

class BvIsListed extends EnumLikeBase
{
    public const int LISTED = 1;

    public const int UNLISTED = 0;

    public const array LABELS = [
        self::LISTED   => '上架',
        self::UNLISTED => '下架',
    ];
}
