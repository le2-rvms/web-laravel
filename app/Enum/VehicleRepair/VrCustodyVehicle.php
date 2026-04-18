<?php

namespace App\Enum\VehicleRepair;

use App\Enum\EnumLikeBase;

class VrCustodyVehicle extends EnumLikeBase
{
    public const string FULL    = 'full';
    public const string PARTIAL = 'partial';

    public const array LABELS = [
        self::FULL    => '全托管',
        self::PARTIAL => '半托管',
    ];
}
