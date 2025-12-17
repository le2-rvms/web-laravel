<?php

namespace App\Enum\Payment;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class IoType extends EnumLikeBase
{
    public const string IN  = 'in';
    public const string OUT = 'out';

    public const string IN_  = 'in_';
    public const string OUT_ = 'out_';

    public const array LABELS = [
        self::IN   => '收款',
        self::OUT  => '付款',
        self::IN_  => '收款→退款',
        self::OUT_ => '付款→退款',
    ];

    public const array colors = [
        self::IN  => Color::ERROR,
        self::OUT => Color::SUCCESS,
    ];
}
