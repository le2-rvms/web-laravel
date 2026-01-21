<?php

namespace App\Console\Commands\Dev;

use App\Attributes\ClassName;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: '_dev:model-lang:generate',
    description: 'Generate a single language package file for all Eloquent models in the project'
)]
class ModelLangGenerate extends Command
{
    public function handle(): void
    {
        $models = $this->getAllModelsUsingReflection();
        $this->generatePropertyLang($models);
        $this->generateModelLang($models);
        $this->info('Language package file generated successfully!');
    }

    private function getAllModelsUsingReflection(): array
    {
        $appPath = app_path('Models');
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

            if ($className && is_subclass_of($className, Model::class)) {
                $models[] = $className;
            }
        }

        return $models;
    }

    private function generatePropertyLang(array $models): void
    {
        $allLanguageContent = [];

        foreach ($models as $model) {
            $modelName  = class_basename($model);
            $properties = $this->parseProperties($model);
            // 如果所有字段描述都是空的，则不生成语言包内容
            if (!empty($properties)) {
                $allLanguageContent[$modelName] = $properties;
            }
        }

        $filePath = lang_path('zh_CN/property.php');

        $directory = dirname($filePath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($filePath, '<?php return '.var_export($allLanguageContent, true).';');

        $this->info("Language package file generated at {$filePath}.");
    }

    private function generateModelLang(array $models): void
    {
        $content = [];

        foreach ($models as $model) {
            $modelName       = class_basename($model);
            $model_attr_name = $this->parseAttrName($model);

            if ($model_attr_name) {
                $content[$modelName] = (array) $model_attr_name;
            }
        }

        $filePath = lang_path('zh_CN/model.php');

        $directory = dirname($filePath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($filePath, '<?php return '.var_export($content, true).';');

        $this->info("Language package file generated at {$filePath}.");
    }

    private function parseProperties(string $model): array
    {
        $properties = [];
        $reflection = new \ReflectionClass($model);
        $docComment = $reflection->getDocComment();
        if (!$docComment) {
            return $properties;
        }

        // 将 DocBlock 按行拆分
        $lines = explode("\n", $docComment);
        foreach ($lines as $line) {
            if (preg_match('/@property\s+\S+\s+\$([a-z_]\w*)(?:\s+(.*))?$/', $line, $parts)) {
                //        }
                //        $result = preg_match('/\$(\S+)\s+(.+)$/', $line, $parts);
                //        if ($result) {
                $propertyName = $parts[1];
                $description  = $parts[2] ?? '';

                //            if (!preg_match('/^[a-z]/', $propertyName)) {
                //                continue;
                //            }

                $description = preg_replace('/[；;]{1}.*$/u', '', trim($description));

                $properties[$propertyName] = $description;
            }
        }

        return $properties;
    }

    private function parseAttrName(string $model)
    {
        $reflection = new \ReflectionClass($model);
        $attributes = $reflection->getAttributes(ClassName::class);
        if (!$attributes) {
            return null;
        }

        foreach ($attributes as $attribute) {
            return $attribute->newInstance();
        }

        return null;
    }
}
