<?php

namespace App\Enum\VehicleViolation;

use App\Enum\EnumLikeBase;

class VvPaymentStatus extends EnumLikeBase
{
    public const int UNPAID = 0;

    public const int PAID = 1;

    public const int PAYMENT_NOT_REQUIRED = 9;

    public const array LABELS = [
        self::UNPAID               => '未交款',
        self::PAID                 => '已缴款',
        self::PAYMENT_NOT_REQUIRED => '无需缴款',
    ];
}
