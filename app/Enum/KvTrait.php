<?php

namespace App\Enum;

trait KvTrait
{
    public static function options(): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = array_map(
            fn ($k, $v) => ['value' => $k, 'text' => $v],
            array_keys(static::kv),
            static::kv
        );

        return [$key => $value];
    }

    public static function kv(): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'kv';
        $value = self::kv;

        return [$key => $value];
    }
}
