<?php

namespace App\Enum\Payment;

use App\Enum\EnumLikeBase;

class PShouldPayDate_DDD extends EnumLikeBase
{
    public const array LABELS = [ // ISO 周编号
        '周一',
        '周二',
        '周三',
        '周四',
        '周五',
        '周六',
        '周日',
    ];

    public static function toCaseSQL(bool $hasAs = true, ?string $fieldName = null): string
    {
        if (!$fieldName) {
            $fieldName = get_field_name(get_called_class());
        }

        $as = preg_replace('/^[^.]*\./', '', $fieldName).'_ddd';

        $caseSQL = "(ARRAY['";
        $caseSQL .= join("','", self::LABELS);
        $caseSQL .= "']) ";
        $caseSQL .= "[EXTRACT(DOW FROM {$fieldName})::int]";
        if ($hasAs) {
            $caseSQL .= " AS {$as}";
        }

        return $caseSQL;
    }
}
