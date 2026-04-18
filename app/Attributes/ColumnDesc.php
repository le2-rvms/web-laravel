<?php

namespace App\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class ColumnDesc
{
    public function __construct(
        public string $column,
        public ?ColumnType $type = null,
        public ?bool $required = false,
        public ?bool $unique = false,
        public ?string $desc = null,
        public ?string $enum_class = null
    ) {}

    public function __toString(): string
    {
        return join('，', array_filter($this->toArray()));
    }

    public function toArray(): array
    {
        return [
            $this->required ? '必填' : '可选',
            $this->unique ? '不可重复' : '',
            $this->getTypeText(),
        ];
    }

    private function getTypeText(): string
    {
        if ($this->enum_class) {
            $labels = $this->enum_class::LABELS;

            return '选项：'.join('、', array_values($labels));
        }

        if ($this->desc) {
            return $this->desc;
        }

        return match ($this->type) {
            //            ColumnType::TEXT => '文本格式',
            ColumnType::DATE     => '内容必须是YYYY-MM-DD，文本格式',
            ColumnType::DATETIME => '内容必须是YYYY-MM-DD HH:MM:SS，文本格式',

            default => '文本格式',
        };
    }
}
