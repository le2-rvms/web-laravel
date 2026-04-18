<?php

namespace App\Enum;

class Enable extends EnumLikeBase
{
    public const int ENABLED  = 1;
    public const int DISABLED = 0;

    public const array LABELS = [
        self::ENABLED  => '启用',
        self::DISABLED => '禁用',
    ];

    public const array colors = [
        self::ENABLED  => Color::SUCCESS,
        self::DISABLED => Color::ERROR,
    ];
}
