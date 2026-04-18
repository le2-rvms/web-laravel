<?php

namespace App\Console\Commands\Sys;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ListRouteControllerMethods extends Command
{
    protected $signature   = '_sys:controllers:methods-from-routes';
    protected $description = 'List all unique controller methods from registered routes';

    public function handle()
    {
        $methods = [];

        foreach (Route::getRoutes() as $route) {
            // 类似 "App\Http\Controllers\UserController@index" 或 "Closure"
            $action = $route->getActionName();

            // 1. 跳过闭包路由
            if ('Closure' === $action) {
                continue;
            }

            // 2. 一般是 "Class@method" 形式
            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action);
            } else {
                // 3. 兼容可调用控制器（单动作控制器），比如 "App\Http\Controllers\FooController"
                $class  = $action;
                $method = '__invoke';
            }

            // 如果你只关心自家 App\Http\Controllers 下的控制器，可以过滤一下：
            if (!str_starts_with($class, 'App\Http\Controllers\\')) {
                continue;
            }

            // 过滤掉魔术方法
            if (str_starts_with($method, '__')) {
                continue;
            }

            $methods[] = $method;
        }

        // 去重 + 排序
        $unique = array_values(array_unique($methods));
        sort($unique);

        $this->info('Unique controller methods from routes:');
        foreach ($unique as $name) {
            $this->line('- '.$name);
        }

        $this->info('Total: '.count($unique));

        return Command::SUCCESS;
    }
}
