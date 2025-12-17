<?php

namespace App\Enum\Payment;

use App\Enum\EnumLikeBase;

class SsDeleteOption extends EnumLikeBase
{
    public const int DELETE        = 1;
    public const int DO_NOT_DELETE = 0;

    public const array LABELS = [
        self::DELETE        => '结算的同时，自动删除全部应收款',
        self::DO_NOT_DELETE => '结算的同时，不删除应收款',
    ];
}
