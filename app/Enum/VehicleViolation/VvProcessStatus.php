<?php

namespace App\Enum\VehicleViolation;

use App\Enum\EnumLikeBase;

class VvProcessStatus extends EnumLikeBase
{
    public const int UNPROCESSED = 0;

    public const int PROCESSED = 1;

    public const int UNPROCESSED2 = 2;

    public const int PROCESSED2 = 9;

    public const array LABELS = [
        self::UNPROCESSED  => '未处理',
        self::PROCESSED    => '已处理',
        self::UNPROCESSED2 => '未处理已人车分离',
        self::PROCESSED2   => '已处理??', // todo
    ];
}
