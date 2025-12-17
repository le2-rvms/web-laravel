<?php

namespace App\Console\Commands\Dev;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ModelDocPropertiesCheck extends Command
{
    protected $signature = '_dev:model:doc-check
        {--path=app/Models : 要扫描的目录（相对项目根目录，可递归）}
        {--models= : 仅检查这些模型（逗号分隔，支持类名或 FQCN）}
        {--ignore=created_at,updated_at,deleted_at,updated_by,processed_by : 忽略的表字段（逗号分隔，不区分大小写）}
        {--include-timestamps : 包含时间戳列（将从忽略列表中移除 created_at/updated_at/deleted_at）}
        {--json : 以 JSON 输出结果}';

    protected $description = '比对模型 DocBlock 中的 @property 与数据表字段，找出多写/少写的属性';

    public function handle(): int
    {
        $scanPath = base_path($this->option('path'));
        if (!is_dir($scanPath)) {
            $this->error("路径不存在：{$scanPath}");

            return self::FAILURE;
        }

        $onlyModels = collect(array_filter(array_map('trim', explode(',', (string) $this->option('models')))))
            ->filter()->map(function ($name) {
                // 允许传短名（例如 User），也允许传完整类名（例如 App\Models\User）
                return ltrim($name, '\\');
            })
        ;

        // 忽略字段（统一转小写）
        $ignore = collect(array_filter(array_map('trim', explode(',', (string) $this->option('ignore')))))
            ->map(fn ($c) => $c)
            ->values()
            ->all()
        ;

        if ($this->option('include-timestamps')) {
            $ignore = array_values(array_diff($ignore, ['created_at', 'updated_at', 'deleted_at']));
        }

        $files   = File::allFiles($scanPath);
        $classes = collect($files)->map(function (\SplFileInfo $file) {
            return $this->extractFqcnFromFile($file);
        })->filter()->values();

        // 仅保留 Eloquent 模型（非抽象类）
        $models = $classes->filter(function (string $fqcn) {
            try {
                if (!class_exists($fqcn)) {
                    // 触发 composer autoload
                    class_exists($fqcn);
                }
                $ref = new \ReflectionClass($fqcn);

                return !$ref->isAbstract() && $ref->isSubclassOf(Model::class);
            } catch (\Throwable $e) {
                return false;
            }
        })->values();

        if ($onlyModels->isNotEmpty()) {
            $models = $models->filter(function ($fqcn) use ($onlyModels) {
                $short = class_basename($fqcn);

                return $onlyModels->contains($fqcn) || $onlyModels->contains($short);
            })->values();
        }

        if ($models->isEmpty()) {
            $this->warn('未发现模型。请确认扫描路径或 --models 参数是否正确。');

            return self::SUCCESS;
        }

        $rows    = [];
        $jsonOut = [];

        foreach ($models as $fqcn) {
            try {
                /** @var Model $instance */
                $instance = new $fqcn();

                $table          = $instance->getTable();
                $connectionName = $instance->getConnectionName();

                $schema = $connectionName
                    ? Schema::connection($connectionName)
                    : Schema::getFacadeRoot();

                $tableExists = false;

                try {
                    $tableExists = $schema->hasTable($table);
                } catch (\Throwable $e) {
                    // 某些驱动下 hasTable 可能抛异常，这里忽略继续尝试列清单
                }

                $columns = [];
                if ($tableExists) {
                    $columns = $connectionName
                        ? Schema::connection($connectionName)->getColumnListing($table)
                        : Schema::getColumnListing($table);
                } else {
                    // 仍尝试获取列，若失败则标记未知表
                    try {
                        $columns = $connectionName
                            ? Schema::connection($connectionName)->getColumnListing($table)
                            : Schema::getColumnListing($table);
                        $tableExists = true;
                    } catch (\Throwable $e) {
                        // 无法获取列
                    }
                }

                // 解析类 DocBlock 中的 @property
                $doc      = (new \ReflectionClass($fqcn))->getDocComment() ?: '';
                $docProps = $this->parsePropertyNamesFromDocBlock($doc);

                // 统一小写比较
                $docSet = collect($docProps)->map(fn ($p) => $p)->unique()->values();
                $colSet = collect($columns)->map(fn ($c) => $c)->unique()->values();

                // 应用忽略列表
                if (!empty($ignore)) {
                    $colSet = $colSet->reject(fn ($c) => in_array($c, $ignore, true))->values();
                    //                    $colSet = $colSet->reject(fn ($c) => !preg_match($ignore,$c))->values();
                }

                $extraInDoc   = $docSet->diff($colSet)->values()->all();    // 注释里多写的
                $missingInDoc = $colSet->diff($docSet)->values()->all();  // 注释里少写的

                $rows[] = [
                    'Model' => $fqcn,
                    'Table' => $table.($tableExists ? '' : ' (NOT FOUND)'),
                    //                    'DocProps'     => $docSet->count(),
                    //                    'Columns'      => $colSet->count(),
                    'MissingInDoc' => implode(', ', $missingInDoc),
                    'ExtraInDoc'   => implode(', ', $extraInDoc),
                ];

                $jsonOut[] = [
                    'model'        => $fqcn,
                    'table'        => $table,
                    'table_exists' => $tableExists,
                    //                    'doc_properties' => $docSet->all(),
                    //                    'columns'        => $colSet->all(),
                    'missing_in_doc' => $missingInDoc,
                    'extra_in_doc'   => $extraInDoc,
                ];
            } catch (\Throwable $e) {
                $rows[] = [
                    'Model'        => $fqcn,
                    'Table'        => '-',
                    'DocProps'     => '-',
                    'Columns'      => '-',
                    'MissingInDoc' => 'ERROR: '.$e->getMessage(),
                    'ExtraInDoc'   => '-',
                ];
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'generated_at' => now()->toDateTimeString(),
                'ignore'       => $ignore,
                'results'      => $jsonOut,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['Model', 'Table', 'MissingInDoc', 'ExtraInDoc'], //  'DocProps', 'Columns'
                $rows
            );
            $this->info('对比完成。提示：默认忽略了 --ignore 中的列，可通过参数自定义。');
        }

        return self::SUCCESS;
    }

    /**
     * 从 PHP 文件中提取 FQCN（命名空间 + 类名）.
     */
    protected function extractFqcnFromFile(\SplFileInfo $file): ?string
    {
        try {
            $code = File::get($file->getRealPath());

            // 命名空间
            $ns = '';
            if (preg_match('/^\s*namespace\s+([^;]+);/m', $code, $m)) {
                $ns = trim($m[1]);
            }

            // 只匹配 class（排除 interface、trait、enum）
            if (!preg_match('/^\s*(?:abstract\s+)?class\s+([A-Za-z_]\w*)/m', $code, $m2)) {
                return null;
            }
            $class = $m2[1];

            return $ns ? ($ns.'\\'.$class) : $class;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function parsePropertyNamesFromDocBlock(string $doc): array
    {
        if (empty($doc)) {
            return [];
        }

        $props = [];
        if (preg_match_all('/@property(?:-read|-write)?\s+[^\$]*\$(\w+)/i', $doc, $m)) {
            $props = $m[1];
        }

        return array_values(array_unique($props));
    }
}
