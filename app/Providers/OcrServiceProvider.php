<?php

namespace App\Providers;

use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;
use Darabonba\OpenApi\Models\Config;
use Illuminate\Support\ServiceProvider;

class OcrServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Ocrapi::class, function ($app) {
            // https://help.aliyun.com/document_detail/311677.html
            $credential = new Credential();

            $config = new Config([
                'credential' => $credential,
            ]);

            // Endpoint 请参考 https://api.aliyun.com/product/ocr-api
            $config->endpoint = 'ocr-api.cn-hangzhou.aliyuncs.com';

            return new Ocrapi($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
