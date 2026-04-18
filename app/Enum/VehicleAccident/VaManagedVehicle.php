<?php

namespace App\Enum\VehicleAccident;

use App\Enum\EnumLikeBase;

class VaManagedVehicle extends EnumLikeBase
{
    public const string FULL_MANAGED = 'full_managed';
    public const string HALF_MANAGED = 'half_managed';

    public const array LABELS = [
        self::FULL_MANAGED => '全托管',
        self::HALF_MANAGED => '半托管',
    ];
}
