<?php

namespace App\Enum\VehicleMaintenance;

use App\Enum\EnumLikeBase;

class VmPickupStatus extends EnumLikeBase
{
    public const string NOT_PICKED_UP = 'not_picked_up';
    public const string PICKED_UP     = 'picked_up';

    public const array LABELS = [
        self::NOT_PICKED_UP => '未提车',
        self::PICKED_UP     => '已提车',
    ];
}
