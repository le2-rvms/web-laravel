<?php

namespace App\Enum\VehicleRepair;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class VrRepairStatus extends EnumLikeBase
{
    public const string PENDING_ENTRY = 'pending_entry';
    public const string IN_PROGRESS   = 'in_progress';
    public const string COMPLETED     = 'completed';

    public const array LABELS = [
        self::PENDING_ENTRY => '待进场',
        self::IN_PROGRESS   => '修理中',
        self::COMPLETED     => '已修好',
    ];

    public const final = self::COMPLETED;

    public const array colors = [
        self::PENDING_ENTRY => Color::PRIMARY,
        self::IN_PROGRESS   => Color::PRIMARY,
        self::COMPLETED     => Color::SUCCESS,
    ];
}
