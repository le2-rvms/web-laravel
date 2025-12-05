<?php

namespace App\Http\Controllers\Base;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request): Response
    {
        $data = [
            'app_is_prod' => app()->isProduction(),
            'app_env'     => app()->environment(),
            'app_debug'   => config('app.debug'),
            'app_name'    => config('app.name'),
            'app_version' => app()->version(),
            'php_version' => phpversion(),
            'os'          => PHP_OS,
            'mock_enable' => config('setting.mock.enable'),
            'host_manual' => config('setting.host_manual'),
        ];

        return $this->response()->withData($data)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
