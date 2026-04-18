<?php

namespace App\Enum\Booking;

use App\Enum\EnumLikeBase;

class BoRefundStatus extends EnumLikeBase
{
    public const string NOREFUND = 'norefund';
    public const string REFUNDED = 'refunded';

    public const array LABELS = [
        self::NOREFUND => '未退款',
        self::REFUNDED => '已退款',
    ];
}
