<?php

namespace App\Enum\Statistics;

use App\Enum\EnumLikeBase;

class Dimension extends EnumLikeBase
{
    public const string DAY     = 'day';
    public const string WEEK    = 'week';
    public const string MONTH   = 'month';
    public const string QUARTER = 'quarter';
    public const string YEAR    = 'year';

    public const array LABELS = [
        self::DAY     => '日',
        self::WEEK    => '周',
        self::MONTH   => '月',
        self::QUARTER => '季度',
        self::YEAR    => '年',
    ];
}
