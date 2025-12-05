<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminIsMock
{
    public function handle(Request $request, \Closure $next): Response
    {
        /** @var Admin $admin */
        $admin = $request->user();
        if ($admin->is_mock ?? false) {
            $method = $request->method();
            if (!in_array($method, ['GET'])) {
                abort(403, '体验模式禁止此操作！');
            }
        }

        return $next($request);
    }
}
