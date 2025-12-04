<?php

namespace App\Enum\Sale;

use App\Enum\EnumLikeBase;

class VrReplacementType extends EnumLikeBase
{
    public const string TEMPORARY = 'temporary';

    public const string PERMANENT = 'permanent'; // todo 去掉永久换车

    public const array LABELS = [
        self::TEMPORARY => '临时换车',
        self::PERMANENT => '永久换车',
    ];
}
