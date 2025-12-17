<?php

namespace App\Enum\Sale;

use App\Enum\EnumLikeBase;

class DtExportType extends EnumLikeBase
{
    public const string DOCX = 'docx';
    public const string PDF  = 'pdf';

    public const array LABELS = [
        self::DOCX => 'word文件',
        self::PDF  => 'PDF文件',
    ];
}
