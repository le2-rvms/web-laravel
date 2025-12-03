<?php

namespace App\Http\Middleware;

use App\Attributes\PermissionAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, \Closure $next)
    {
        if ($this->checkRequestPermission($request)) {
            return $next($request);
        }
    }

    private function checkRequestPermission(Request $request): bool
    {
        $actionName = $request->route()->getActionName();

        $actionArray = explode('@', $actionName);

        if (2 !== sizeof($actionArray)) {
            return true;
        }

        [$controllerName, $method] = $actionArray;

        // 反射获取方法注解
        $actionName_ = str_replace('@', '::', $actionName);

        try {
            $reflectionMethod = new \ReflectionMethod($actionName_);
        } catch (\ReflectionException $e) {
            return false;
        }

        $permissionAttributes = $reflectionMethod->getAttributes(PermissionAction::class);

        if (!$permissionAttributes) {
            return true;
        }

        /** @var PermissionAction $permissionAttributeIns */
        $permissionAttributeIns = $permissionAttributes[0]->newInstance();

        $controller_shortname = preg_replace('{Controller$}', '', class_basename($controllerName));

        $admin = Auth::user();
        if ($admin && $admin->can($controller_shortname.'::'.$permissionAttributeIns->name)) {
            return true;
        }

        abort(403, trans('You have not permission to this page!'));
    }
}
