<?php

namespace App\Http\Middleware;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogRequests
{
    /** 缓存编译后的脱敏规则（通配符、点路径） */
    protected array $compiledMaskRules;

    /** 缓存被屏蔽的头部名（小写） */
    protected array $blockedHeaders;

    /** 构造里把配置一次性读好，避免每次 handle/terminate 反复取配置 */
    public function __construct()
    {
        $this->compiledMaskRules = $this->compileMaskRules(
            array_merge(
                // 默认字段
                [
                    'password', 'password_confirmation', 'current_password',
                    '*token*', 'client_secret', 'secret',
                    'authorization', 'cookie', 'cookies',
                ],
                (array) config('log.mask_fields', [])
            )
        );

        $this->blockedHeaders = array_map(
            'strtolower',
            (array) config('log.mask_headers', ['authorization', 'cookie', 'set-cookie'])
        );
    }

    public function handle(Request $request, \Closure $next)
    {
        if (!config('log.enabled', true)) {
            return $next($request);
        }

        // 路径白名单
        foreach ((array) config('log.ignore_paths', ['up', 'health*', 'metrics']) as $pattern) {
            if ($this->pathIs($request->path(), $pattern)) {
                return $next($request);
            }
        }

        // 方法白名单（可选）
        $allowedMethods = (array) config('log.methods', []); // 为空=不过滤
        if ($allowedMethods && !in_array($request->getMethod(), $allowedMethods, true)) {
            return $next($request);
        }

        // 采样
        $sample = (float) config('log.sample_rate', 1.0);
        if ($sample < 1.0 && mt_rand() / mt_getrandmax() > $sample) {
            return $next($request);
        }

        $start = microtime(true);
        $rid   = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        // 记录给 terminate
        $request->attributes->set('log.request_start', $start);
        $request->attributes->set('log.request_id', $rid);

        // —— 记录请求（注意：任何异常都要吞掉，避免影响主流程）
        try {
            Log::channel('reqres')->info('request', [
                'rid'     => $rid,
                'ip'      => $request->ip(),
                'method'  => $request->getMethod(),
                'uri'     => '/'.ltrim($request->path(), '/'),
                'query'   => $this->truncateValues($request->query()),
                'headers' => $this->filterHeaders($request->headers->all()),
                'body'    => $this->extractRequestBody($request),
            ]);
        } catch (\Throwable $e) {
            // 静默失败：不影响业务
        }

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (!config('log.enabled', true)) {
            return;
        }

        $start      = $request->attributes->get('log.request_start') ?? microtime(true);
        $rid        = $request->attributes->get('log.request_id') ?? (string) Str::uuid();
        $durationMs = round((microtime(true) - $start) * 1000, 2);

        $body = null;

        try {
            if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
                $body = '[binary/streamed response omitted]';
            } elseif ($response instanceof JsonResponse) {
                $data = $response->getData(true);
                $body = $this->maskAndLimit($data);
            } elseif ($response instanceof Response && method_exists($response, 'getContent')) {
                $body = $this->truncateString($response->getContent(), (int) config('log.max_text_length', 5000));
            }
        } catch (\Throwable $e) {
            $body = '[response capture failed]';
        }

        try {
            Log::channel('reqres')->info('response', [
                'rid'         => $rid,
                'status'      => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'duration_ms' => $durationMs,
                'headers'     => $response instanceof Response
                    ? $this->filterHeaders($response->headers->all())
                    : [],
                'body'        => $body,
                'memory_peak' => memory_get_peak_usage(true),
            ]);
        } catch (\Throwable $e) {
            // 静默
        }
    }

    // ========================== 内部工具方法 ==========================

    /** 轻量提取请求体：优先解析 JSON；multipart 仅记录文件元信息；最后兜底 form/urlencoded */
    protected function extractRequestBody(Request $request)
    {
        $maxKeys     = (int) config('log.max_body_keys', 2000);     // 过大对象采样
        $maxPerField = (int) config('log.max_field_length', 2000);  // 单字段限长

        $ct = strtolower((string) $request->headers->get('Content-Type', ''));

        // multipart：只记录字段+文件元信息
        if (str_starts_with($ct, 'multipart/form-data')) {
            $fields = $this->maskAndLimit($request->request->all(), $maxKeys, $maxPerField);
            $files  = [];
            foreach ($request->files->all() as $key => $file) {
                if (is_array($file)) {
                    $files[$key] = array_map(fn ($f) => [
                        'clientName' => $f?->getClientOriginalName(),
                        'size'       => $f?->getSize(),
                    ], $file);
                } else {
                    $files[$key] = $file ? [
                        'clientName' => $file->getClientOriginalName(),
                        'size'       => $file->getSize(),
                    ] : null;
                }
            }

            return ['_multipart' => true, 'fields' => $fields, 'files' => $files];
        }

        // application/json：不触发 Laravel 再次序列化的开销
        if (str_contains($ct, 'application/json')) {
            $raw = $request->getContent();
            if ('' === $raw || null === $raw) {
                return null;
            }
            $decoded = json_decode($raw, true);
            // 无法解析就按字符串限长存一份样本，避免写全量
            if (JSON_ERROR_NONE !== json_last_error()) {
                return $this->truncateString($raw, (int) config('log.max_text_length', 5000));
            }

            return $this->maskAndLimit($decoded, $maxKeys, $maxPerField);
        }

        // 其它：大多是 form-urlencoded 或无体
        return $this->maskAndLimit($request->request->all(), $maxKeys, $maxPerField);
    }

    /** 过滤/打码头部 */
    protected function filterHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            if (in_array($lk, $this->blockedHeaders, true)) {
                $out[$k] = ['[redacted]'];
            } else {
                // 限制头部数组长度与单值长度，防止某些巨大头部
                $vals    = is_array($v) ? $v : [$v];
                $vals    = array_slice($vals, 0, (int) config('log.max_header_values', 10));
                $vals    = array_map(fn ($s) => $this->truncateString((string) $s, (int) config('log.max_header_length', 1000)), $vals);
                $out[$k] = $vals;
            }
        }

        return $out;
    }

    /** 同时做脱敏 + 字段限长 + 采样（避免超大对象打爆日志） */
    protected function maskAndLimit($data, ?int $maxKeys = null, ?int $maxPerField = null)
    {
        if (!is_array($data)) {
            return $data;
        }
        $maxKeys     = $maxKeys ?? (int) config('log.max_body_keys', 2000);
        $maxPerField = $maxPerField ?? (int) config('log.max_field_length', 2000);

        // 扁平化为点路径，便于匹配规则（* 通配）
        $flat    = Arr::dot($data);
        $trimmed = [];
        $count   = 0;
        foreach ($flat as $path => $val) {
            if ($count++ >= $maxKeys) {
                $trimmed['__notice__'] = 'body trimmed due to key limit';

                break;
            }
            $trimmed[$path] = is_scalar($val) || null === $val
                ? $this->truncateString((string) $val, $maxPerField)
                : $val;
        }

        // 脱敏
        $redacted = [];
        foreach ($trimmed as $path => $val) {
            if ($this->pathMatched($path, $this->compiledMaskRules)) {
                $redacted[$path] = config('log.redacted_text', '[redacted]');
            } else {
                $redacted[$path] = $val;
            }
        }

        return Arr::undot($redacted);
    }

    /** 单字符串限长（多字节安全） */
    protected function truncateString(?string $s, int $limit): ?string
    {
        if (null === $s) {
            return null;
        }
        if (mb_strlen($s, 'UTF-8') <= $limit) {
            return $s;
        }

        return mb_substr($s, 0, $limit, 'UTF-8').'...(truncated)';
    }

    /** 将 mask 规则编译为正则（支持通配符 * 和点路径） */
    protected function compileMaskRules(array $rules): array
    {
        $compiled = [];
        foreach ($rules as $rule) {
            $rule = strtolower(trim((string) $rule));
            // 点路径 or 通配字段名 *xxx*
            $pattern    = '/^'.str_replace('\*', '.*', preg_quote($rule, '/')).'$/i';
            $compiled[] = $pattern;
        }

        return $compiled;
    }

    /** 检查点路径是否匹配任一规则 */
    protected function pathMatched(string $dotPath, array $compiled): bool
    {
        $dotPathLower = strtolower($dotPath);
        foreach ($compiled as $regex) {
            if (preg_match($regex, $dotPathLower)) {
                return true;
            }
        }
        // 额外：也尝试仅字段名（最后一段），对 rule=“*token*” 类更友好
        $last = strtolower((string) last(explode('.', $dotPathLower)));
        foreach ($compiled as $regex) {
            if (preg_match($regex, $last)) {
                return true;
            }
        }

        return false;
    }

    /** 简易路径匹配：支持 * 通配 */
    protected function pathIs(string $path, string $pattern): bool
    {
        $pattern = str_replace('\*', '.*', preg_quote(trim($pattern, '/'), '/'));

        return (bool) preg_match('/^'.$pattern.'$/i', trim($path, '/'));
    }

    /** 对数组所有标量值做限长（用于 query 等） */
    protected function truncateValues($data, int $limit = 1000)
    {
        if (!is_array($data)) {
            return $data;
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->truncateValues($v, $limit);
            } elseif (is_scalar($v) || null === $v) {
                $data[$k] = $this->truncateString((string) $v, $limit);
            }
        }

        return $data;
    }
}
