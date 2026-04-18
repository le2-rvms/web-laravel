<?php

namespace App\Enum\VehicleAccident;

use App\Enum\EnumLikeBase;

class VaSettlementMethod extends EnumLikeBase
{
    public const string INSIDE_CONTRACT  = 'inside_contract';
    public const string OUTSIDE_CONTRACT = 'outside_contract';
    public const string DRIVER_SELF      = 'driver_self';

    public const array LABELS = [
        self::INSIDE_CONTRACT  => '承包内',
        self::OUTSIDE_CONTRACT => '承包外',
        self::DRIVER_SELF      => '司机自费',
    ];
}
