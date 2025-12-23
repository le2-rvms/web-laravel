<?php

namespace App\Enum;

trait ArrayTrait
{
    private static ?array $keyValues = null;

    public static function options(): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = array_map(function ($region) {
            return [
                'text'  => $region[static::option_text_key],
                'value' => $region[static::option_value_key],
            ];
        }, static::values);

        return [$key => $value];
    }

    public static function getKeys(): array
    {
        return array_map(function ($region) {
            return $region[static::option_text_key];
        }, static::values);
    }

    public static function columnValues(?string $key = null)
    {
        if (null === static::$keyValues) {
            static::$keyValues = array_column(static::values, null, static::option_text_key);
        }
        if (null !== $key) {
            return static::$keyValues[$key];
        }

        return static::$keyValues;
    }
}
