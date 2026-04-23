<?php

namespace App\Enum\Config;

use App\Enum\EnumLikeBase;

class CpVerifyStatus extends EnumLikeBase
{
    public const int UNVERIFIED = 0;

    public const int VERIFIED = 1;

    public const array LABELS = [
        self::UNVERIFIED => '未认证',
        self::VERIFIED   => '已认证',
    ];
}
