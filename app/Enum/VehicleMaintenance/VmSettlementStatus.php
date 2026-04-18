<?php

namespace App\Enum\VehicleMaintenance;

use App\Enum\EnumLikeBase;

class VmSettlementStatus extends EnumLikeBase
{
    public const string UNSETTLED = 'unsettled';
    public const string SETTLED   = 'settled';
    public const string CONFIRMED = 'confirmed';

    public const array LABELS = [
        self::UNSETTLED => '未结算',
        self::SETTLED   => '已结算',
        self::CONFIRMED => '已确认',
    ];
}
