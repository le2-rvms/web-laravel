<?php

namespace App\Enum\VehicleAccident;

use App\Enum\Color;
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

    public const array colors = [
        self::PENDING_ENTRY => Color::WARNING,
        self::REPAIRING     => Color::PRIMARY,
        self::REPAIRED      => Color::SUCCESS,
    ];
}
