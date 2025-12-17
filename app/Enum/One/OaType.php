<?php

namespace App\Enum\One;

use App\Enum\EnumLikeBase;

class OaType extends EnumLikeBase
{
    public const string PERSON = 'person';

    public const string COMPANY = 'company';

    public const array LABELS = [
        self::PERSON  => '个人',
        self::COMPANY => '公司',
    ];
}
