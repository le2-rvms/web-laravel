<?php

namespace App\Enum\Admin;

use App\Enum\EnumLikeBase;

class AUserType extends EnumLikeBase
{
    public const int COMMON = 0;
    public const int TEMP   = 1; // 临时账号,由command 生成的，通常是超级用户

    public const int MOCK = 2; // 演示账号

    public const array LABELS = [
        self::COMMON => '一般账号',
        self::TEMP   => '临时账号',
        self::MOCK   => '演示账号',
    ];
}
