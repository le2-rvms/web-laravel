<?php

namespace App\Enum\VehicleAccident;

use App\Enum\EnumLikeBase;

class VaClaimStatus extends EnumLikeBase
{
    public const string PROCESSING = 'processing';
    public const string COMPLETED  = 'completed';

    public const array LABELS = [
        self::PROCESSING => '处理中',
        self::COMPLETED  => '已完成',
    ];
}
