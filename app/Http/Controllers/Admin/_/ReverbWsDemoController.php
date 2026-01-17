<?php

namespace App\Http\Controllers\Admin\_;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReverbWsDemoController extends Controller
{
    public static function labelOptions(Controller $controller): void {}

    //    public function index(Request $request): Response
    //    {
    //        $defaults              = $this->buildDefaults($request, url('/broadcasting/auth'));
    //        $defaults['csrfToken'] = csrf_token();
    //
    //        return response()->view('Admin.Reverb.ws_demo', [
    //            'defaults' => $defaults,
    //            'mode'     => 'session',
    //        ]);
    //    }

    public function token(Request $request): Response
    {
        $defaults = $this->buildDefaults($request, url('/api-admin/broadcasting/auth'));

        return response()->view('Admin.Reverb.ws_demo', [
            'defaults' => $defaults,
            'mode'     => 'token',
        ]);
    }

    public function vueDemo(Request $request): Response
    {
        $defaults = $this->buildDefaults($request, url('/api-admin/broadcasting/auth'));

        return response()->view('Admin.Reverb.vue_demo', [
            'defaults' => $defaults,
        ]);
    }

    //    public function echoDemo(Request $request): Response
    //    {
    //        return response()->view('Admin.Reverb.echo_demo', [
    //        ]);
    //    }

    protected function options(?bool $with_group_count = false): void {}

    private function buildDefaults(Request $request, string $authEndpoint): array
    {
        $config  = config('broadcasting.connections.reverb', []);
        $options = $config['options'] ?? [];

        return [
            'host'         => ($options['host'].'.'.config('app.host_domain_base')),
            'port'         => config('app.gw_port'),
            'scheme'       => 'wss',
            'appKey'       => (string) ($config['key'] ?? ''),
            'path'         => (string) (config('reverb.servers.reverb.path') ?? ''),
            'channel'      => (string) $request->query('channel', ''),
            'authEndpoint' => $authEndpoint,
        ];
    }
}
