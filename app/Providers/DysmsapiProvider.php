<?php

namespace App\Providers;

use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use Darabonba\OpenApi\Models\Config;
use Illuminate\Support\ServiceProvider;

class DysmsapiProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Dysmsapi::class, function ($app) {
            // 使用默认凭证初始化Credentials Client。
            $credential = new Credential();
            $config     = new Config([
                'credential' => $credential,
            ]);

            // Endpoint 请参考 https://api.aliyun.com/product/Dysmsapi
            $config->endpoint = 'dysmsapi.aliyuncs.com';

            return new Dysmsapi($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
