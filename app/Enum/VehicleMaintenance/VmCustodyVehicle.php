<?php

namespace App\Enum\VehicleMaintenance;

use App\Enum\EnumLikeBase;

class VmCustodyVehicle extends EnumLikeBase
{
    public const string FULL    = 'full';
    public const string PARTIAL = 'partial';

    public const array LABELS = [
        self::FULL    => '全托管',
        self::PARTIAL => '半托管',
    ];
}
