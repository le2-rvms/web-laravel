<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VePendingStatusRental extends EnumLikeBase
{
    public const string UNRENTED = 'unrented';

    public const string PENDING = 'pending';

    public const string RESERVED = 'reserved';

    public const array LABELS = [
        self::UNRENTED => '未上架',
        self::PENDING  => '已上架',
        self::RESERVED => '已预定',
    ];
}
