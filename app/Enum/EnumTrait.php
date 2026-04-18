<?php

namespace App\Enum;

trait EnumTrait
{
    public static function kv(): array
    {
        $key    = preg_replace('/^.*\\\/', '', get_called_class()).'Kv';
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->name] = $case->value;
        }

        return [
            $key => $result,
        ];
    }
}
