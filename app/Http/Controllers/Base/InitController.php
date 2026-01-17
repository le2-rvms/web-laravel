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
        // 前端初始化所需的环境信息。
        // 字段名与前端约定保持一致，便于直接消费。
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
            'ws'          => (function () {
                $config  = config('broadcasting.connections.reverb', []);
                $options = $config['options'] ?? [];

                return [
                    'host'          => ($options['host'].'.'.config('app.host_domain_base')),
                    'port'          => config('app.gw_port'),
                    'scheme'        => 'wss',
                    'app_key'       => (string) ($config['key'] ?? ''),
                    'path'          => (string) (config('reverb.servers.reverb.path') ?? ''),
                    'auth_endpoint' => route('api-admin.broadcasting.auth', absolute: false),
                ];
            })(),
        ];

        return $this->response()->withData($data)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
