<?php

/**
 * 将数字金额转换为大写中文金额（含元、角、分），支持负数，不做四舍五入，只截断到分.
 *
 * @param null|string $amount 原始金额，可以为负数
 *
 * @return null|string 中文大写金额
 */
function money_format_zh(?string $amount): ?string
{
    if (null === $amount) {
        return '';
    }

    // 数值化并判断正负
    $value      = (float) $amount;
    $isNegative = $value < 0;
    $absValue   = abs($value);

    static $digits       = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
    static $sectionUnits = ['', '万', '亿', '兆'];
    static $smallUnits   = ['', '拾', '佰', '仟'];

    // 不四舍五入，直接截断到分
    $totalFen = (int) ($absValue * 100);
    $intPart  = intdiv($totalFen, 100);
    $decPart  = $totalFen % 100;

    // —— 整数部分 —— //
    if (0 === $intPart) {
        $intStr = $digits[0];
    } else {
        // 切分 4 位一节
        $sections = [];
        while ($intPart > 0) {
            $sections[] = $intPart % 10000;
            $intPart    = intdiv($intPart, 10000);
        }

        // 自高向低组装
        $intStr   = '';
        $hasValue = false;
        $cnt      = count($sections);
        for ($i = $cnt - 1; $i >= 0; --$i) {
            $section = $sections[$i];
            if ($section > 0) {
                // 若需要跨节补零
                if ($hasValue
                    && $section < 1000
                    && !str_ends_with($intStr, $digits[0])
                ) {
                    $intStr .= $digits[0];
                }
                // 本节转换 + 大单位
                $intStr .= convertSection($section, $digits, $smallUnits)
                    .$sectionUnits[$i];
                $hasValue = true;
            } elseif ($hasValue) {
                // 本节全零且后面还有非零节，则补一个零
                $hasLower = false;
                for ($j = 0; $j < $i; ++$j) {
                    if ($sections[$j] > 0) {
                        $hasLower = true;

                        break;
                    }
                }
                if ($hasLower && !str_ends_with($intStr, $digits[0])) {
                    $intStr .= $digits[0];
                }
            }
        }
    }
    $intStr .= '元';

    // —— 小数部分（角、分） —— //
    $jiao   = intdiv($decPart, 10);
    $fen    = $decPart % 10;
    $decStr = '';

    if ($jiao > 0) {
        $decStr .= $digits[$jiao].'角';
    } elseif ($fen > 0) {
        // 整元后无角但有分时补“零”
        $decStr .= $digits[0];
    }

    if ($fen > 0) {
        $decStr .= $digits[$fen].'分';
    }

    // 如果没有分，则补“整”
    if (0 === $fen) {
        $decStr .= '整';
    }

    $result = $intStr.$decStr;

    return $isNegative ? '负'.$result : $result;
}

/**
 * 将 0~9999 的数字节转换为中文（不含大单位）.
 *
 * @param int   $section    数字节（0~9999）
 * @param array $digits     数字映射表
 * @param array $smallUnits 单位映射表
 *
 * @return string 中文表达
 */
function convertSection(int $section, array $digits, array $smallUnits): string
{
    $str     = '';
    $unitPos = 0;

    while ($section > 0) {
        $digit = $section % 10;
        if ($digit > 0) {
            $str = $digits[$digit]
                .$smallUnits[$unitPos]
                .$str;
        } elseif (!str_starts_with($str, $digits[0])) {
            $str = $digits[0].$str;
        }
        $section = intdiv($section, 10);
        ++$unitPos;
    }

    // 去掉本节末尾可能多出的“零”
    return rtrim($str, $digits[0]);
}
