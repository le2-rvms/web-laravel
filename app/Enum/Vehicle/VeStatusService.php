<?php

namespace App\Enum\Vehicle;

use App\Enum\Color;
use App\Enum\YesNo;

class VeStatusService extends YesNo
{
    public const array LABELS = [
        self::YES => '运营中',
        self::NO  => '未运营',
    ];

    public const array colors = [
        self::YES => Color::SUCCESS,
        self::NO  => Color::WARNING,
    ];
}
