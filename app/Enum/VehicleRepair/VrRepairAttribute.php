<?php

namespace App\Enum\VehicleRepair;

use App\Enum\EnumLikeBase;

class VrRepairAttribute extends EnumLikeBase
{
    public const string ROUTINE       = 'routine';
    public const string INSURANCE     = 'insurance';
    public const string RECTIFICATION = 'rectification';
    public const string WARRANTY      = 'warranty';
    public const string PREPARATION   = 'preparation';
    public const string ACCIDENT      = 'accident';

    public const array LABELS = [
        self::ROUTINE       => '日常维修',
        self::INSURANCE     => '出险维修',
        self::RECTIFICATION => '整改维修',
        self::WARRANTY      => '质保维修',
        self::PREPARATION   => '整备维修',
        self::ACCIDENT      => '事故维修',
    ];
}
