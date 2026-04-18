<?php

namespace App\Enum\Sale;

use App\Enum\EnumLikeBase;

class DtFileType extends EnumLikeBase
{
    public const string WORD = 'word';
    public const string HTML = 'html';

    public const array LABELS = [
        self::WORD => 'word模板',
        self::HTML => '在线编辑',
    ];
}
