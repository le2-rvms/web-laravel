<?php

namespace App\Console\Commands\Sys;

use App\Attributes\PermissionNoneType;
use App\Attributes\PermissionType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_sys:permission:import3',
    description: ''
)]
class PermissionImport3 extends Command
{
    protected $signature   = '_sys:permission:import3';
    protected $description = '';

    private array $controllers;

    private array $lang_zh_CN;

    public function handle(): int
    {
        Artisan::call('permission:cache-reset');

        $this->getAllControllers();

        (function (): void {
            $filePath = lang_path('zh_CN/controller.php');

            $directory = dirname($filePath);

            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($filePath, '<?php return '.var_export($this->lang_zh_CN, true).';');

            $this->info("Language package file generated at {$filePath}");
        })();

        DB::transaction(function () {
            $permissionNames = Permission::query()->lockForUpdate()->get()->pluck('', 'name')->keys()->toArray();

            foreach ($this->controllers as $controller) {
                list($group_name, $name, $title) = $controller;

                Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['title' => $title, 'group_name' => $group_name]
                );

                if (($key = array_search($name, $permissionNames)) !== false) {
                    unset($permissionNames[$key]);
                }
            }

            Permission::query()->whereIn('name', $permissionNames)->delete();
        });

        return CommandAlias::SUCCESS;
    }

    private function getAllControllers(): void
    {
        $controllers = getAllControllers('Http/Controllers/Admin');

        $names = [];
        $zh_CN = [];
        foreach ($controllers as $controllerClass) {
            $reflectionClass = new \ReflectionClass($controllerClass);

            foreach ([PermissionType::class, PermissionNoneType::class] as $type) {
                $attributes = $reflectionClass->getAttributes($type);

                if (!$attributes) {
                    continue;
                }

                // 假设一个方法只有一个 Permission 属性
                /** @var PermissionNoneType|PermissionType $permissionAttribute */
                $permissionAttribute = $attributes[0]->newInstance();

                $title = $permissionAttribute->zh_CN;

                $name = preg_replace('{Controller$}', '', $reflectionClass->getShortName());

                $group_name = Str::afterLast($reflectionClass->getNamespaceName(), '\\');

                if (PermissionType::class === $type) {
                    $names[] = [$group_name, $name, $title];
                }

                $zh_CN[$name] = $title;
            }
        }

        $this->controllers = $names;

        $this->lang_zh_CN = $zh_CN;
    }
}
