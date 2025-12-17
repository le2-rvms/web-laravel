<?php

namespace App\Enum\Payment;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class PPayStatus extends EnumLikeBase
{
    public const string UNPAID = 'unpaid';
    public const string PAID   = 'paid';

    public const array LABELS = [
        self::UNPAID => '未支付',
        self::PAID   => '已支付',
    ];

    public const array colors = [
        self::UNPAID => Color::ERROR,
        self::PAID   => Color::SUCCESS,
    ];
}
