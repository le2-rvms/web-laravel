<?php

namespace App\Enum\VehicleAccident;

use App\Enum\EnumLikeBase;

class VaRepairStatus extends EnumLikeBase
{
    public const string REPAIRING     = 'repairing';
    public const string REPAIRED      = 'repaired';
    public const string PENDING_ENTRY = 'pending_entry';

    public const array LABELS = [
        self::PENDING_ENTRY => '待进场',
        self::REPAIRING     => '维修中',
        self::REPAIRED      => '已修好',
    ];
}
