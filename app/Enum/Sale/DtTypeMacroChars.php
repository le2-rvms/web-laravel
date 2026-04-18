<?php

namespace App\Enum\Sale;

use App\Enum\EnumTrait;

enum DtTypeMacroChars: string
{
    use EnumTrait;
    case Opening = '{{';

    case Closing = '}}';
}
