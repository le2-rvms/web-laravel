<?php

namespace App\Console\Commands\App;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WecomCacheAccessToken extends Command
{
    protected $signature = 'app:wecom:cache-token
                            {--print : 输出 access_token }
                            ';

    protected $description = '获取企业微信 access_token 并写入缓存';

    public function handle(): int
    {
        $cacheKey = config('setting.wecom.app_delivery_token_cache_key');

        if ($this->option('print') && Cache::has($cacheKey)) {
            $this->line((string) Cache::get($cacheKey));

            return self::SUCCESS;
        }

        $corpId     = config('setting.wecom.corp_id');
        $corpSecret = config('setting.wecom.app_delivery_secret');

        if (!$corpId || !$corpSecret) {
            $this->error('缺少 WECOM_CORP_ID 或 WECOM_CORP_SECRET，请检查 .env / config。');

            return self::FAILURE;
        }

        $token = Cache::get($cacheKey);
        if ($token) {
            $this->line('token:'.$token);

            return self::SUCCESS;
        }

        try {
            $resp = Http::timeout(10)
                ->retry(3, 1000) // 简单重试
                ->get('https://qyapi.weixin.qq.com/cgi-bin/gettoken', [
                    'corpid'     => $corpId,
                    'corpsecret' => $corpSecret,
                ])
            ;
            Log::channel('console')->info($resp);
        } catch (\Throwable $e) {
            $this->error('请求企业微信失败：'.$e->getMessage());

            return self::FAILURE;
        }

        if (!$resp->ok()) {
            $this->error('企业微信接口 HTTP 非 200，状态码：'.$resp->status());

            return self::FAILURE;
        }

        $json = $resp->json();

        if (($json['errcode'] ?? 0) !== 0) {
            $this->error("企业微信返回错误：{$json['errcode']} {$json['errmsg']}");

            return self::FAILURE;
        }

        $token     = $json['access_token'] ?? null;
        $expiresIn = (int) $json['expires_in']; // 秒
        if (!$token) {
            $this->error('响应中未包含 access_token。');

            return self::FAILURE;
        }

        // 预留 buffer，避免边界过期
        $buffer = (int) config('setting.wecom.cache_ttl_buffer');
        $ttl    = max(60, $expiresIn - $buffer);

        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        $this->info("access_token 已缓存，TTL：{$ttl} 秒。");
        if ($this->option('print')) {
            $this->line($token);
        }

        return self::SUCCESS;
    }
}
