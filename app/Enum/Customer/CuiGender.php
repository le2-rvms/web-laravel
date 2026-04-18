<?php

namespace App\Enum\Customer;

use App\Enum\EnumLikeBase;

class CuiGender extends EnumLikeBase
{
    public const string MALE   = 'male';
    public const string FEMALE = 'female';

    public const array LABELS = [
        self::MALE   => '男',
        self::FEMALE => '女',
    ];
}
