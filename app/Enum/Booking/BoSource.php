<?php

namespace App\Enum\Booking;

use App\Enum\EnumLikeBase;

class BoSource extends EnumLikeBase
{
    public const string STORE = 'store';

    public const string MP = 'mp';

    public const array LABELS = [
        self::STORE => '门店',
        self::MP    => '小程序',
    ];
}
