<?php

namespace App\Console\Commands\Sys;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelCastsCheck extends Command
{
    protected $signature   = '_sys:models-casts:check';
    protected $description = 'List casts in all models and validate ::class casts against attribute key (studly case).';

    public function handle(): int
    {
        $modelsPath = app_path('Models');

        if (!File::exists($modelsPath)) {
            $this->error('Models directory not found: '.$modelsPath);

            return self::FAILURE;
        }

        $failed = false;

        $files = File::allFiles($modelsPath);
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $fqn = $this->getFqnFromFile($file->getRealPath());
            if (!$fqn) {
                // 可能是 trait / interface / 匿名类 / 非标准写法
                continue;
            }

            if (!class_exists($fqn)) {
                $this->warn("Skip (class not autoloadable): {$fqn}");

                continue;
            }

            try {
                $model = new $fqn();
            } catch (\Throwable $e) {
                $this->warn("Skip (cannot instantiate): {$fqn}. Reason: {$e->getMessage()}");

                continue;
            }

            if (!$model instanceof EloquentModel) {
                // 不是 Eloquent 模型就跳过
                continue;
            }

            $casts = $model->getCasts();
            if (empty($casts)) {
                continue;
            }

            $this->info('Model: '.class_basename($fqn));

            foreach ($casts as $attribute => $castValue) {
                // 仅对 “值是类/枚举” 的 cast 校验（等价于你说的源代码里用 ::class）
                if (!$this->isClassLikeCast($castValue)) {
                    continue;
                }

                $expected = Str::studly($attribute);          // oa_type => OaType
                $actual   = class_basename($castValue);       // \App\Enums\OaType => OaType

                if ($expected !== $actual) {
                    $failed = true;
                    $this->error("  [FAIL] {$attribute} => {$actual} (expected: {$expected})");
                } else {
                    $this->line("  [OK]   {$attribute} => {$actual}");
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function isClassLikeCast(mixed $castValue): bool
    {
        if (!is_string($castValue)) {
            return false;
        }

        // 过滤掉常见内置 cast（int/string/bool/array/datetime:... 等）
        if (str_contains($castValue, ':')) {
            return false;
        }

        // 自定义 Cast 类 / Enum / 其他可 autoload 的类
        return class_exists($castValue) || (function_exists('enum_exists') && enum_exists($castValue));
    }

    private function getFqnFromFile(string $path): ?string
    {
        $contents = File::get($path);

        // namespace
        if (!preg_match('/^namespace\s+([^;]+);/m', $contents, $nsMatch)) {
            return null;
        }
        $namespace = trim($nsMatch[1]);

        // class（兼容 final/abstract）
        if (!preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $classMatch)) {
            return null;
        }
        $class = trim($classMatch[1]);

        return $namespace.'\\'.$class;
    }
}
