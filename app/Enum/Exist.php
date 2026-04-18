<?php

namespace App\Enum;

class Exist extends EnumLikeBase
{
    public const int HAVE = 1;
    public const int MISS = 0;

    public const array LABELS = [
        self::HAVE => '有',
        self::MISS => '无',
    ];
}
