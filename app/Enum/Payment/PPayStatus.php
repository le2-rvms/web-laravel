<?php

namespace App\Enum\Payment;

use App\Enum\Color;
use App\Enum\EnumLikeBase;

class PPayStatus extends EnumLikeBase
{
    public const string UNPAID = 'unpaid';

    public const string PAID = 'paid';

    public const string NO_NEED_PAY = 'no_need_pay'; // 新增：无需支付

    public const array LABELS = [
        self::UNPAID      => '未支付',
        self::PAID        => '已支付',
        self::NO_NEED_PAY => '无需支付',
    ];

    public const array colors = [
        self::UNPAID      => Color::WARNING,
        self::PAID        => Color::SUCCESS,
        self::NO_NEED_PAY => Color::PRIMARY, // 若你没有 INFO，可改成 SUCCESS / PRIMARY / DEFAULT 等你项目里存在的颜色
    ];
}
