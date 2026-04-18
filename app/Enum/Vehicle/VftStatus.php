<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VftStatus extends EnumLikeBase
{
    public const string COLLECTED = 'collected';

    public const string REDEEMED = 'redeemed';

    public const array LABELS = [
        self::COLLECTED => '已收车',
        self::REDEEMED  => '已赎车',
    ];
}
