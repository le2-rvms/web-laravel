<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VeType extends EnumLikeBase
{
    public const string SMALL_VEHICLE            = '02';
    public const string SMALL_NEW_ENERGY_VEHICLE = '52'; // 数字的字符串需要特别处理！

    public const array LABELS = [
        self::SMALL_VEHICLE            => '小型汽车',
        self::SMALL_NEW_ENERGY_VEHICLE => '小型新能源汽车',
    ];

    public const array KEYS = [self::SMALL_VEHICLE, self::SMALL_NEW_ENERGY_VEHICLE];

    public static function options(): array
    {
        $class = get_called_class();

        return
            [
                preg_replace('/^.*\\\/', '', $class).'Options' => array_map(
                    function ($k, $v) use ($class) {
                        $text = $v.((static::$options_groups[$class] ?? null) ? ('('.(static::$options_groups[$class][$k] ?? 0).')') : '');

                        return ['text' => $text, 'value' => $k];
                    },
                    //  static fn ($k, $v) => ['text' => $v, 'value' => $k],
                    static::KEYS,
                    static::LABELS
                ),
            ];
    }
}
