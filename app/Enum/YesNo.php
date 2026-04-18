<?php

namespace App\Enum;

class YesNo extends EnumLikeBase
{
    public const int YES = 1; // 是

    public const int NO = 0;  // 否

    public const array LABELS = [
        self::YES => '是',
        self::NO  => '否',
    ];
}
