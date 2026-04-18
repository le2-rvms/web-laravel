<?php

namespace App\Enum\Config;

use App\Enum\EnumLikeBase;

class CfgUsageCategory extends EnumLikeBase
{
    public const int APP    = 0;
    public const int SYSTEM = 1;

    public const array LABELS = [
        self::APP    => '应用',
        self::SYSTEM => '系统',
    ];
}
