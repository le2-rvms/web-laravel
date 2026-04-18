<?php

namespace App\Models\_;

trait ImportTrait
{
    // 由具体模型定义可导入字段与映射。
    public static array $fields = [];

    // 定义字段映射与导入列配置。
    abstract public static function importColumns(): array;

    // 导入前的字段预处理（类型转换/清洗）。
    abstract public static function importBeforeValidateDo(): \Closure;

    // 单行数据的规则校验。
    abstract public static function importValidatorRule(array $item, array $fieldAttributes): void;

    // 全量导入后的跨行校验。
    abstract public static function importAfterValidatorDo(): \Closure;

    // 实际持久化动作（创建/更新）。
    abstract public static function importCreateDo(): \Closure;
}
