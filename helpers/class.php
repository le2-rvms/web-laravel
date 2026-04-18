<?php

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * 从 composer classmap 中查找指定短类名的全量命名空间.
 */
function getNamespaceByComposerMap(string $shortName, string $include): ?string
{
    $map = require base_path('vendor/composer/autoload_classmap.php');
    foreach (array_keys($map) as $fullClass) {
        if (class_basename($fullClass) === $shortName && class_exists($fullClass)) {
            if ($include) {
                if (Str::contains($fullClass, $include)) {
                    return $fullClass;
                }
            } else {
                return $fullClass;
            }
        }
    }

    return null;
}

/**
 * 根据表名和主键值，载入 Composer 的 classmap，找到对应模型并返回 Eloquent 实例.
 *
 * @return Model
 *
 * @throws ModelNotFoundException
 */
function getModel(string $baseName)
{
    // 1. 由表名生成模型名称后缀，如 'users' → 'User'
    //    $baseName = Str::studly(Str::singular($name));
    //    $baseName = $name;

    // 2. 载入 Composer 的 classmap（FullClassName => filePath）
    $classMap = require base_path('vendor/composer/autoload_classmap.php');

    // 3. 筛选出所有以 \User 结尾的完整类名
    $candidates = array_filter(
        array_keys($classMap),
        fn ($class) => Str::endsWith($class, '\\'.$baseName)
    );

    if (empty($candidates)) {
        throw new ModelNotFoundException("模型 {$baseName} 在 classmap 中未找到");
    }

    // 4. 直接取第一个匹配的候选类
    $modelClass = reset($candidates);

    // 5. 手动加载（若还未被 Composer 自动加载）
    if (!class_exists($modelClass)) {
        require_once $classMap[$modelClass];
    }

    return $modelClass;
    // 6. 使用 Eloquent 的 findOrFail 拿到实例，找不到时自动抛 404
}

function getAllControllers($path): array
{
    $appPath = app_path($path);
    $files   = File::allFiles($appPath);
    $models  = [];

    $base_path = base_path();

    $get_class = function ($path) use ($base_path) {
        $path = preg_replace(["{^{$base_path}/}", '{\.php$}'], '', $path);

        // 按正斜杠分割路径
        $parts = explode('/', $path);

        // 根据需要对每一部分进行处理，如首字母大写
        $parts = array_map(function ($part) {
            return ucfirst($part);
        }, $parts);

        // 使用反斜杠拼接为命名空间
        return implode('\\', $parts);
    };

    foreach ($files as $file) {
        if ('php' !== $file->getExtension()) {
            continue;
        }

        $className = $get_class($file->getRealPath());

        if ($className && is_subclass_of($className, Controller::class)) {
            $models[] = $className;
        }
    }

    return $models;
}
