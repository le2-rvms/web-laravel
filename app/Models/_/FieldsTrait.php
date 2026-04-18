<?php

namespace App\Models\_;

use App\Enum\Sale\DtTypeMacroChars;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

trait FieldsTrait
{
    protected bool $fieldsFullMode = true;

    public function setFieldMode($fieldsFullMode): static
    {
        $this->fieldsFullMode = $fieldsFullMode;

        return $this;
    }

    public function getFieldsAndRelations(array $relations, ?array $attrs = null): array
    {
        $result = [];

        // —— 属性 ——
        if ($this->fieldsFullMode) {
            $result['_'] = $this->parseDocblockProperties($this);
        } else {
            $result = $this->parseDocblockProperties($this);
        }

        // —— 关联外键 ——
        if ($relations) {
            foreach ($relations as $relation) {
                $parts        = explode('.', $relation);
                $partsCount   = count($parts);
                $currentModel = $this;

                $prefixes = [];

                foreach ($parts as $index => $part) {
                    /** @var Relation $relObj */
                    $relObj = $currentModel->{$part}();

                    // drill down 到 Related 模型的空实例
                    $currentModel = $relObj->getRelated();

                    if ($index === $partsCount - 1) {
                        $resultKey = (function () use ($parts) {
                            // 先按 . 拆分，再把每段 CamelCase 转成 snake_case，最后再用 . 连接起来
                            $snakeParts = array_map(
                                fn (string $part) => Str::snake($part),
                                $parts
                            );

                            return join('.', $snakeParts);
                        })();

                        $result[$resultKey] = $this->parseDocblockProperties($currentModel, $prefixes);
                    } else {
                        $prefixes[] = trans_model($currentModel);
                    }
                }
            }
        }

        // ——  ——
        if ($attrs) {
            foreach ($attrs as $attr) {
                $parts        = explode('.', $attr);
                $partsCount   = count($parts);
                $currentModel = $this;

                $prefixes = [];

                foreach ($parts as $index => $currentModel) {
                    if ($index === $partsCount - 1) {
                        $resultKey = (function () use ($parts) {
                            // 先按 . 拆分，再把每段 CamelCase 转成 snake_case，最后再用 . 连接起来
                            $snakeParts = array_map(
                                fn (string $part) => Str::snake($part),
                                $parts
                            );

                            return join('.', $snakeParts);
                        })();

                        $result[$resultKey] = $this->parseDocblockProperties($currentModel, $prefixes);
                    } else {
                        $prefixes[] = trans_model($currentModel);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param mixed $model
     * @param mixed $prefixes
     */
    private function parseDocblockProperties(Model $model, array $prefixes = []): array
    {
        $results = trans_property($model);

        $hidden = $this->getHidden();

        // 过滤掉 hidden
        $results = array_filter(
            $results,
            function ($desc, $name) use ($hidden) {
                return !in_array($name, $hidden, true);
            },
            ARRAY_FILTER_USE_BOTH
        );

        $open  = DtTypeMacroChars::Opening->value;
        $close = DtTypeMacroChars::Closing->value;

        if ($this->fieldsFullMode) {
            $shortNameLang = trans_model($model);

            return [
                'title'      => $shortNameLang,
                'prefixes'   => $prefixes,
                'properties' => $results,
                'group_tpl'  => implode("\n", array_map(fn ($v) => $v.' '.$open.$v.$close.' ', $results)),
            ];
        }

        return $results;
    }
}
