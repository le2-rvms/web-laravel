<?php

namespace App\Enum\VehicleRepair;

use App\Enum\EnumLikeBase;

class VrSettlementMethod extends EnumLikeBase
{
    public const string INTERNAL = 'internal';
    public const string EXTERNAL = 'external';
    public const string DRIVER   = 'driver';

    public const array LABELS = [
        self::INTERNAL => '承包内',
        self::EXTERNAL => '承包外',
        self::DRIVER   => '司机自费',
    ];
}
