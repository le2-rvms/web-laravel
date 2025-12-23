<?php

namespace App\Enum\Admin;

use App\Enum\EnumLikeBase;

class ATeamLimit extends EnumLikeBase
{
    public const int NOT_LIMITED = 0;
    public const int LIMITED     = 1;

    public const array LABELS = [
        self::NOT_LIMITED => '不限定车队',
        self::LIMITED     => '限定车队',
    ];
}
