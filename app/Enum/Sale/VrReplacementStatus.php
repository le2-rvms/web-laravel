<?php

namespace App\Enum\Sale;

use App\Enum\EnumLikeBase;

class VrReplacementStatus extends EnumLikeBase
{
    public const string IN_PROGRESS = 'in_progress';

    public const string COMPLETED = 'completed';

    public const array LABELS = [
        self::IN_PROGRESS => '换车中',
        self::COMPLETED   => '已结束',
    ];
}
