<?php

namespace App\Enum;

use App\Exceptions\ClientException;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[\AllowDynamicProperties]
abstract class EnumLikeBase implements Castable
{
    protected static ?array $options_groups = null;

    public function __construct(public readonly mixed $value = null)
    {
        if (null !== $value) {
            $this->label = static::LABELS[$value] ?? null;
        }
    }

    public function __toString()
    {
        return $this->value;
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        $enumClass = static::class;

        return new readonly class($enumClass) implements CastsAttributes {
            public function __construct(private string $enumClass) {}

            /** 读取：数据库值 -> 值对象实例 */
            public function get(Model $model, string $key, mixed $value, array $attributes): ?object
            {
                if (null === $value) {
                    return null;
                }

                $labels = $this->enumClass::LABELS;

                if (array_key_exists($value, $labels)) {
                    return new ($this->enumClass)($value);
                }

                return null;
                //                throw new \InvalidArgumentException("Invalid {$key} value: ".var_export($value, true));
            }

            /** 写入：各种输入 -> 底层存储值（string/int） */
            public function set(Model $model, string $key, mixed $value, array $attributes): mixed
            {
                if (null === $value) {
                    return null;
                }

                if ($value instanceof $this->enumClass) {
                    return $value->value; // 返回标量
                }

                $labels = $this->enumClass::LABELS;

                if (array_key_exists($value, $labels)) {
                    return $value; // 返回标量
                }

                throw new \InvalidArgumentException("Invalid {$key} value: ".var_export($value, true));
            }

            /** 控制 toArray()/toJson() 时的输出 */
            public function serialize(Model $model, string $key, mixed $value, array $attributes): mixed
            {
                return $value instanceof $this->enumClass ? $value->value : $value;
            }
        };
    }

    public static function toCaseSQL(bool $hasAs = true, ?string $fieldName = null): string
    {
        if (!$fieldName) {
            $fieldName = get_field_name(get_called_class());
        }

        $as = preg_replace('/^[^.]*\./', '', $fieldName).'_label';

        $entries = [];
        foreach (static::LABELS as $key => $value) {
            $entries[] = "'{$key}', '{$value}'";
        }

        return sprintf(
            ' (jsonb_build_object (%s) ->> %s::TEXT) %s',
            implode(', ', $entries),
            $fieldName,
            $hasAs ? " as {$as}" : ''
        );
    }

    public static function toColorSQL(?string $fieldName = null): string
    {
        if (!$fieldName) {
            $fieldName = get_field_name(get_called_class());
        }

        $as = preg_replace('/^[^.]*\./', '', $fieldName).'_color';

        $caseSQL = "CASE {$fieldName} ";
        foreach (static::colors as $key => $value) {
            $color_code = 'uni-'.$value;
            $caseSQL .= "WHEN '{$key}' THEN'{$color_code}' ";
        }
        $caseSQL .= " ELSE '' ";
        $caseSQL .= 'END';
        $caseSQL .= " as {$as}";

        return $caseSQL;
    }

    public static function finalValue(): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'FinalValue';

        return [$key => static::final];
    }

    public static function labelOptions(?string $fieldName = null): array
    {
        if (!$fieldName) {
            $fieldName = get_field_short_name(get_called_class());
        }

        return [$fieldName.'LabelOptions' => static::LABELS];
    }

    public static function options(): array
    {
        $class = get_called_class();
        $key   = preg_replace('/^.*\\\/', '', $class).'Options';

        return [
            $key => array_map(
                function ($k, $v) use ($class) {
                    $text = $v.((static::$options_groups[$class] ?? null) ? ('('.(static::$options_groups[$class][$k] ?? 0).')') : '');

                    return ['text' => $text, 'value' => $k];
                },
                //  static fn ($k, $v) => ['text' => $v, 'value' => $k],
                array_keys(static::LABELS),
                static::LABELS
            ),
        ];
    }

    public static function options_with_count(string $group_by_model_class_name): array
    {
        $class = get_called_class();

        /** @var Model $group_by_model_class_name */
        $fieldName = get_field_short_name($class);

        static::$options_groups[$class] = $group_by_model_class_name::query()
            ->select($fieldName, DB::raw('COUNT(*) as count'))
            ->groupBy($fieldName)
            ->pluck('count', $fieldName)
        ;

        return static::options();
    }

    public static function label_key_random(): int|string
    {
        return array_rand(static::LABELS);
    }

    public static function label_keys(): array
    {
        return array_keys(static::LABELS);
    }

    public static function searchValue(?string $label): ?string
    {
        if (null === $label || '' === $label) {
            return null;
        }
        // 用 array_search 查出对应的枚举值（backed value）
        $value = array_search($label, static::LABELS, true);

        if (false === $value) {
            throw new ClientException("无效的类型标签label：{$label}");
        }

        return $value;
    }

    public static function tryFrom($key): ?static
    {
        if (null === $key || '' === $key) {
            return null;
        }

        if (array_key_exists($key, static::LABELS)) {
            return new static($key);
        }

        //        throw new ClientException("无效的类型标签key：{$key}");
        throw new \InvalidArgumentException("Invalid {$key} value: ".self::class);
    }

    public static function labelDic(): array
    {
        return [preg_replace('/^.*\\\/', '', get_called_class()).'LabelDic' => static::LABELS];
    }

    public static function flipLabelDic(): array
    {
        return [preg_replace('/^.*\\\/', '', get_called_class()).'FlipLabelDic' => array_flip(static::LABELS)];
    }
}
