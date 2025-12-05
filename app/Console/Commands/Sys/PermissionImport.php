<?php

namespace App\Console\Commands\Sys;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * 以控制器为单位，叠加「读、写」维度。
 */
#[AsCommand(
    name: '_sys:permission:import',
    description: ''
)]
class PermissionImport extends Command
{
    protected $signature   = '_sys:permission:import';
    protected $description = '';

    private array $permissions = [];
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

            foreach ($this->permissions as $group_name => $classes) {
                foreach ($classes as $class_name => $permissions) {
                    foreach ($permissions as $permission => $title) {
                        Permission::query()->updateOrCreate(['name' => $name = $class_name.'::'.$permission], ['title' => $title, 'group_name' => $group_name]);

                        if (($key = array_search($name, $permissionNames)) !== false) {
                            unset($permissionNames[$key]);
                        }
                    }
                }
            }

            Permission::query()->whereIn('name', $permissionNames)->delete();
        });

        return CommandAlias::SUCCESS;
    }

    private function getAllControllers()
    {
        $routeCollection = Route::getRoutes();

        //        $permissions = [];
        foreach ($routeCollection as $route) {
            $reflectionMethod = null;

            $actionName = str_replace('@', '::', $route->getActionName());

            if (!str_contains($actionName, 'App\Http\Controllers\Admin\\')) {
                continue;
            }

            try {
                $reflectionMethod = new \ReflectionMethod($actionName);
            } catch (\ReflectionException $e) {
                continue;
            }

            $permissionAttributes = $reflectionMethod->getAttributes(PermissionAction::class);

            if (!$permissionAttributes) {
                continue;
            }

            /** @var PermissionAction $permissionAttributeIns */
            $permissionAttributeIns = $permissionAttributes[0]->newInstance();

            // controller
            $controllerClass = $reflectionMethod->getDeclaringClass()->getName();

            $reflectionClass = new \ReflectionClass($controllerClass);

            $PermissionTypAttributes = $reflectionClass->getAttributes(PermissionType::class);

            if (!$PermissionTypAttributes) {
                continue;
            }

            // 假设一个方法只有一个 Permission 属性
            /** @var PermissionType $permissionTypeAttribute */
            $permissionTypeAttribute = $PermissionTypAttributes[0]->newInstance();

            $classTitle = $permissionTypeAttribute->zh_CN;

            $className = preg_replace('{Controller$}', '', $reflectionClass->getShortName());

            $groupName = Str::afterLast($reflectionClass->getNamespaceName(), '\\');

            $this->permissions[$groupName][$className][$permissionAttributeIns->name] = $classTitle.'-'.trans('app.permission_groups.'.$permissionAttributeIns->name);

            $this->lang_zh_CN[$className] = $classTitle;
        }
    }
}
