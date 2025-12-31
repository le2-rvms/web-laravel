<?php

namespace App\Console\Commands\App\One;

use App\Enum\One\OaType;
use App\Models\One\OneAccount;
use App\Models\One\OneRequest;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[AsCommand(
    name: '_app:one-html:fetch',
    description: 'Fetch vehicle information from ?.122.gov.cn and save request and response to database'
)]
class OneHtmlFetch extends Command
{
    protected $signature   = '_app:one-html:fetch {--turn=}';
    protected $description = 'Fetch vehicle information from sc.122.gov.cn and save request and response to database';

    private string $turn;

    public function handle(): int
    {
        $this->turn = $this->option('turn') ?: Carbon::now()->format('Y-m-d');

        $oneAccounts = OneAccount::getListForFetch();

        foreach ($oneAccounts as $oneAccount) {
            switch ($oneAccount->oa_type) {
                case OaType::PERSON:
                    $this->personVehicle($oneAccount);
                    $this->personViolation($oneAccount);

                    break;

                case OaType::COMPANY:
                    $this->companyVehicle($oneAccount, hpzl: '02');
                    $this->companyVehicle($oneAccount, hpzl: '52');
                    $this->companyViolation($oneAccount);
                    $this->companyViolation($oneAccount);

                    break;

                default:
                    break;
            }
        }

        return CommandAlias::SUCCESS;
    }

    /**
     * 机动车信息管理 > 小型汽车.
     */
    private function companyVehicle(OneAccount $oneAccount, string $hpzl): void
    {
        OneRequest::query()
            ->where('turn', '=', $this->turn)
            ->where('key', 'like', "vehs,{$hpzl},%")
            ->where('or_status_code', '!=', '200')->delete()
        ;

        $domain = $oneAccount->province_value['url'];

        // 请求的 URL
        $url = $domain.'/user/m/userinfo/vehs';

        // 请求头
        $headers = [
            'Accept'             => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Language'    => 'zh',
            'Connection'         => 'keep-alive',
            'Content-Type'       => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin'             => $domain,
            'Referer'            => $domain.'/views/memfyy/vehinfo.html?index=7',
            'Sec-Fetch-Dest'     => 'empty',
            'Sec-Fetch-Mode'     => 'cors',
            'Sec-Fetch-Site'     => 'same-origin',
            'User-Agent'         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
            'X-Requested-With'   => 'XMLHttpRequest',
            'sec-ch-ua'          => '"Not A(Brand";v="8", "Chromium";v="132", "Google Chrome";v="132"',
            'sec-ch-ua-mobile'   => '?0',
            'sec-ch-ua-platform' => '"macOS"',
        ];

        // 初始表单数据
        $formData = [
            'page'   => '1',
            'size'   => '10',
            'status' => 'null',
            'hpzl'   => $hpzl,
            'hphm'   => '',
        ];

        $currentPage = 1;
        $isLastPage  = false;

        while (!$isLastPage) {
            $task_key = 'vehs,'.$hpzl.','.$currentPage;

            $vehicle122Request = OneRequest::query()
                ->where([
                    'turn' => $this->turn,
                    'key'  => $task_key,
                ])->first()
            ;
            if ($vehicle122Request) {
                $this->info("车辆列表 {$task_key} 记录已存在。");

                // 解析响应内容
                $responseData = $vehicle122Request->response;
            } else {
                $delaySeconds = 2;
                $this->info("暂停 {$delaySeconds} 秒后开始...");
                sleep(2); // 暂停指定的秒数

                $this->info("正在请求第 {$currentPage} 页...");

                // 更新当前页数
                $formData['page'] = (string) $currentPage;

                // 将请求信息保存到数据库
                $vehRequest = OneRequest::query()->create([
                    'turn'      => $this->turn,
                    'key'       => $task_key,
                    'url'       => $url,
                    'headers'   => json_encode($headers, JSON_UNESCAPED_UNICODE),
                    'form_data' => json_encode($formData, JSON_UNESCAPED_UNICODE),
                ]);

                try {
                    // 发送 POST 请求
                    $response = Http::withHeaders($headers)
                        ->withHeaders([
                            'Cookie' => $oneAccount->oa_cookie_string,
                        ])
                        ->asForm()
                        ->post($url, $formData)
                    ;

                    // 检查响应状态并保存
                    if ($response->successful()) {
                        $this->info("第 {$currentPage} 页请求成功！");
                        Log::channel('console')->info("第 {$currentPage} 页请求成功。响应内容：", [$response->body()]);
                    } else {
                        $this->error("第 {$currentPage} 页请求失败，状态码：{$response->status()}");
                        Log::channel('console')->warning("第 {$currentPage} 页请求失败，状态码：{$response->status()}");
                    }

                    // 更新数据库记录
                    $vehRequest->update([
                        'or_status_code' => $response->status(),
                        'response'       => $response->body(),
                    ]);

                    $responseData = $response->body();
                } catch (\Throwable $e) {
                    $vehRequest->update([
                        'response' => 'Error: '.$e->getMessage(),
                    ]);

                    $this->error("第 {$currentPage} 页请求过程中发生错误：{$e->getMessage()}");
                    Log::channel('console')->error("第 {$currentPage} 页请求过程中发生错误：{$e->getMessage()}");

                    break;
                }
            }

            // 解析响应内容
            $responseData = json_decode($responseData, true);

            if (isset($responseData['data']['last'])) {
                $isLastPage = $responseData['data']['last'];
                $this->info("第 {$currentPage} 页的 last 值为：".($isLastPage ? 'true' : 'false'));
            } else {
                $this->error("无法解析第 {$currentPage} 页的 last 状态，假设为最后一页。");
                Log::channel('console')->error("无法解析第 {$currentPage} 页的 last 状态。");
                $isLastPage = true;
            }

            // 如果不是最后一页，准备请求下一页
            if (!$isLastPage) {
                ++$currentPage;
            }
        }

        $this->info('所有页请求完成。');
    }

    private function companyViolation($oneAccount): void
    {
        OneRequest::query()
            ->where('turn', $this->turn)
            ->where('response', 'like', '%服务异常%')->delete()
        ;
        OneRequest::query()
            ->where('turn', $this->turn)
            ->where('or_status_code', '!=', '200')->delete()
        ;

        $requests = OneRequest::query()
            ->where('turn', $this->turn)
            ->where('key', 'like', 'vehs,%')
            ->orderBy('or_id')->get()
        ;

        $domain = $oneAccount->oa_province_value['url'];

        foreach ($requests as $request) {
            $response = json_decode($request->response, true);
            if ($content = $response['data']['content'] ?? false) {
                foreach ($content as $contentItem) {
                    // 机动车状态  "ztStr": "正常", "ztStr": "违法未处理",
                    // 号牌号码  "hphm": "川GT4C63",
                    // 车辆类型	"hpzl": "02", "hpzlStr": "小型汽车",
                    // 检验有效期止 "yxqz": "2025-06-30",
                    // 强制报废期止 "qzbfqz": "--",
                    // 电子监控  "dzjk": "12",

                    $currentPage = 1;
                    $isLastPage  = false;

                    // 准备违章查询的表单数据
                    $violationFormData = [
                        'startDate' => date('Y0101'),
                        'endDate'   => date('Ymd'),
                        'hpzl'      => $contentItem['hpzl'],
                        'hphm'      => $contentItem['hphm'],
                        'page'      => '1',
                        'type'      => 0,
                    ];

                    while (!$isLastPage) {
                        if (OneRequest::query()
                            ->where([
                                'turn' => $this->turn,
                                'key'  => 'violation,'.$contentItem['hphm'].','.$currentPage,
                            ])->exists()) {
                            $this->info("车辆 {$contentItem['hphm']} 的违章查记录已存在。");

                            continue 2;
                        }

                        $delaySeconds = 2;
                        $this->info("暂停 {$delaySeconds} 秒后开始...");
                        sleep($delaySeconds); // 暂停指定的秒数

                        // 更新当前页数
                        $violationFormData['page'] = (string) $currentPage;

                        // 请求的违章查询 URL
                        $violationUrl = $domain.'/user/m/uservio/suriquery';

                        // 请求头可能略有不同，您可以根据需要调整
                        $violationHeaders = [
                            'Accept'             => 'application/json, text/javascript, */*; q=0.01',
                            'Accept-Language'    => 'zh',
                            'Connection'         => 'keep-alive',
                            'Content-Type'       => 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Origin'             => $domain,
                            'Referer'            => $domain.'/views/memfyy/violation.html',
                            'Sec-Fetch-Dest'     => 'empty',
                            'Sec-Fetch-Mode'     => 'cors',
                            'Sec-Fetch-Site'     => 'same-origin',
                            'User-Agent'         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
                            'X-Requested-With'   => 'XMLHttpRequest',
                            'sec-ch-ua'          => '"Not A(Brand";v="8", "Chromium";v="132", "Google Chrome";v="132"',
                            'sec-ch-ua-mobile'   => '?0',
                            'sec-ch-ua-platform' => '"macOS"',
                        ];

                        // 保存违章查询请求信息到数据库
                        $violationRequest = OneRequest::query()->create([
                            'turn'      => $this->turn,
                            'key'       => 'violation,'.$contentItem['hphm'].','.$currentPage,
                            'url'       => $violationUrl,
                            'headers'   => json_encode($violationHeaders, JSON_UNESCAPED_UNICODE),
                            'form_data' => json_encode($violationFormData, JSON_UNESCAPED_UNICODE),
                        ]);

                        try {
                            // 发送违章查询 POST 请求
                            $violationResponse = Http::withHeaders($violationHeaders)
                                ->withHeaders([
                                    'Cookie' => $oneAccount->oa_cookie_string,
                                ])
                                ->asForm()
                                ->post($violationUrl, $violationFormData)
                            ;

                            // 检查响应状态并保存
                            if ($violationResponse->successful()) {
                                $this->info("车辆 {$contentItem['hphm']} 的违章查询成功！");
                                Log::channel('console')->info("车辆 {$contentItem['hphm']} 的违章查询成功。响应内容：", [$violationResponse->body()]);
                            } else {
                                $this->error("车辆 {$contentItem['hphm']} 的违章查询失败，状态码：{$violationResponse->status()}");
                                Log::channel('console')->warning("车辆 {$contentItem['hphm']} 的违章查询失败，状态码：{$violationResponse->status()}");
                            }

                            // 更新违章查询数据库记录
                            $violationRequest->update([
                                'or_status_code' => $violationResponse->status(),
                                'response'       => $violationResponse->body(),
                            ]);

                            // 解析响应内容
                            $responseData = json_decode($violationResponse->body(), true);

                            if (isset($responseData['data']['last'])) {
                                $isLastPage = $responseData['data']['last'];
                                $this->info("第 {$currentPage} 页的 last 值为：".($isLastPage ? 'true' : 'false'));
                            } else {
                                $this->error("无法解析第 {$currentPage} 页的 last 状态，假设为最后一页。");
                                Log::channel('console')->error("无法解析第 {$currentPage} 页的 last 状态。");
                                $isLastPage = true;
                            }

                            // 如果不是最后一页，准备请求下一页
                            if (!$isLastPage) {
                                ++$currentPage;
                            }
                        } catch (\Throwable $e) {
                            // 捕获异常并更新违章查询数据库记录
                            $violationRequest->update([
                                'response' => 'Error: '.$e->getMessage(),
                            ]);

                            $this->error("车辆 {$contentItem['hphm']} 的违章查询过程中发生错误：{$e->getMessage()}");
                            Log::channel('console')->error("车辆 {$contentItem['hphm']} 的违章查询过程中发生错误：{$e->getMessage()}");
                        }
                    }
                }
            }
        }
    }

    private function personVehicle(OneAccount $oneAccount): void
    {
        $affect_rows = OneRequest::query()
            ->where('turn', '=', $this->turn)
            ->where('key', 'like', "allvehs,{$oneAccount->oa_name},%")
            ->where('or_status_code', '!=', '200')
            ->delete()
        ;

        $domain = $oneAccount->oa_province_value['url'];

        // 请求的 URL
        $url = $domain.'/user/m/userinfo/allvehs';

        // 请求头
        $headers = [
            'Accept'             => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Language'    => 'zh',
            'Connection'         => 'keep-alive',
            'Content-Type'       => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin'             => $domain,
            'Referer'            => $domain.'/views/member/',
            'Sec-Fetch-Dest'     => 'empty',
            'Sec-Fetch-Mode'     => 'cors',
            'Sec-Fetch-Site'     => 'same-origin',
            'User-Agent'         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
            'X-Requested-With'   => 'XMLHttpRequest',
            'sec-ch-ua'          => '"Not A(Brand";v="8", "Chromium";v="132", "Google Chrome";v="132"',
            'sec-ch-ua-mobile'   => '?0',
            'sec-ch-ua-platform' => '"macOS"',
        ];

        // 初始表单数据
        $formData = [
            'page'   => '1',
            'size'   => '999',
            'status' => 'null',
        ];

        $currentPage = 1;
        $isLastPage  = false;

        while (!$isLastPage) {
            $task_key = 'allvehs,'.$oneAccount->oa_name.','.$currentPage;

            $vehicle122Request = OneRequest::query()
                ->where([
                    'turn' => $this->turn,
                    'key'  => $task_key,
                ])->first()
            ;
            if ($vehicle122Request) {
                $this->info("车辆列表 {$task_key} 记录已存在。");

                // 解析响应内容
                $responseData = $vehicle122Request->response;
            } else {
                $delaySeconds = 2;
                $this->info("暂停 {$delaySeconds} 秒后开始...");
                sleep(2); // 暂停指定的秒数

                $this->info("正在请求第 {$currentPage} 页...");

                // 更新当前页数
                $formData['page'] = (string) $currentPage;

                // 将请求信息保存到数据库
                $vehRequest = OneRequest::query()->create([
                    'turn'      => $this->turn,
                    'key'       => $task_key,
                    'url'       => $url,
                    'headers'   => json_encode($headers, JSON_UNESCAPED_UNICODE),
                    'form_data' => json_encode($formData, JSON_UNESCAPED_UNICODE),
                ]);

                try {
                    $client = new Client();

                    $cookieJar = $oneAccount->initializeCookies();

                    $response = $client->post($url, [
                        'headers'         => $headers,
                        'cookies'         => $cookieJar,
                        'form_params'     => $formData,
                        'allow_redirects' => false,
                    ]);

                    // 检查响应状态并保存
                    $statusCode   = $response->getStatusCode();
                    $responseData = $response->getBody()->getContents();

                    if (200 === $statusCode) {
                        $this->info("第 {$currentPage} 页请求成功！");
                        Log::channel('console')->info("第 {$currentPage} 页请求成功。响应内容：", [$responseData]);
                    } else {
                        $this->error("第 {$currentPage} 页请求失败，状态码：{$statusCode}");
                        Log::channel('console')->warning("第 {$currentPage} 页请求失败，状态码：{$statusCode}");
                    }

                    // 更新数据库记录
                    $vehRequest->update([
                        'or_status_code' => $statusCode,
                        'response'       => $responseData,
                    ]);
                } catch (\Throwable $e) {
                    $vehRequest->update([
                        'response' => 'Error: '.$e->getMessage(),
                    ]);

                    $this->error("第 {$currentPage} 页请求过程中发生错误：{$e->getMessage()}");
                    Log::channel('console')->error("第 {$currentPage} 页请求过程中发生错误：{$e->getMessage()}");

                    break;
                }
            }

            // 解析响应内容
            $responseData = json_decode($responseData, true);

            if (isset($responseData['data']['last'])) {
                $isLastPage = $responseData['data']['last'];
                $this->info("第 {$currentPage} 页的 last 值为：".($isLastPage ? 'true' : 'false'));
            } else {
                $this->error("无法解析第 {$currentPage} 页的 last 状态，假设为最后一页。");
                Log::channel('console')->error("无法解析第 {$currentPage} 页的 last 状态。");
                $isLastPage = true;
            }

            // 如果不是最后一页，准备请求下一页
            if (!$isLastPage) {
                ++$currentPage;
            }
        }

        $this->info('所有页请求完成。');
    }

    private function personViolation(OneAccount $oneAccount): void
    {
        $affect_rows = OneRequest::query()
            ->where('turn', $this->turn)
            ->where('key', 'like', 'violation,%')
            ->where('response', 'like', '%服务异常%')
            ->delete()
        ;
        $affect_rows = OneRequest::query()
            ->where('turn', $this->turn)
            ->where('key', 'like', 'violation,%')
            ->where('or_status_code', '!=', '200')
            ->delete()
        ;

        $requests = OneRequest::query()
            ->where('turn', $this->turn)
            ->where('key', 'like', 'allvehs,'.$oneAccount->oa_name.'%')
            ->orderBy('or_id')->get()
        ;

        $domain = $oneAccount->oa_province_value['url'];

        foreach ($requests as $request) {
            $response = json_decode($request->response, true);
            if ($content = $response['data']['content'] ?? false) {
                foreach ($content as $contentItem) {
                    // 机动车状态  "ztStr": "正常", "ztStr": "违法未处理",
                    // 号牌号码  "hphm": "川GT4C63",
                    // 车辆类型	"hpzl": "02", "hpzlStr": "小型汽车",
                    // 检验有效期止 "yxqz": "2025-06-30",
                    // 强制报废期止 "qzbfqz": "--",
                    // 电子监控  "dzjk": "12",

                    $currentPage = 1;
                    $isLastPage  = false;

                    // 准备违章查询的表单数据
                    $violationFormData = [
                        'hpzl' => $contentItem['hpzl'],
                        'hphm' => $contentItem['hphm'],
                        'page' => '1',
                        'size' => '10',
                    ];

                    while (!$isLastPage) {
                        if (OneRequest::query()
                            ->where([
                                'turn' => $this->turn,
                                'key'  => 'violation,'.$contentItem['hphm'].','.$currentPage,
                            ])->exists()) {
                            $this->info("车辆 {$contentItem['hphm']} 的违章查记录已存在。");

                            continue 2;
                        }

                        $delaySeconds = 2;
                        $this->info("暂停 {$delaySeconds} 秒后开始...");
                        sleep($delaySeconds); // 暂停指定的秒数

                        // 更新当前页数
                        $violationFormData['page'] = (string) $currentPage;

                        // 请求的违章查询 URL
                        $violationUrl = $domain.'/user/m/uservio/vehsvios';

                        // 请求头可能略有不同，您可以根据需要调整
                        $violationHeaders = [
                            'Accept'             => 'application/json, text/javascript, */*; q=0.01',
                            'Accept-Language'    => 'zh',
                            'Connection'         => 'keep-alive',
                            'Content-Type'       => 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Origin'             => $domain,
                            'Sec-Fetch-Dest'     => 'empty',
                            'Sec-Fetch-Mode'     => 'cors',
                            'Sec-Fetch-Site'     => 'same-origin',
                            'User-Agent'         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
                            'X-Requested-With'   => 'XMLHttpRequest',
                            'sec-ch-ua'          => '"Not A(Brand";v="8", "Chromium";v="132", "Google Chrome";v="132"',
                            'sec-ch-ua-mobile'   => '?0',
                            'sec-ch-ua-platform' => '"macOS"',
                        ];

                        // 保存违章查询请求信息到数据库
                        $violationRequest = OneRequest::query()->create([
                            'turn'      => $this->turn,
                            'key'       => 'violation,'.$contentItem['hphm'].','.$currentPage,
                            'url'       => $violationUrl,
                            'headers'   => json_encode($violationHeaders, JSON_UNESCAPED_UNICODE),
                            'form_data' => json_encode($violationFormData, JSON_UNESCAPED_UNICODE),
                        ]);

                        try {
                            $client = new Client();

                            $cookieJar = $oneAccount->initializeCookies();

                            $response = $client->post($violationUrl, [
                                'headers'         => $violationHeaders,
                                'cookies'         => $cookieJar,
                                'form_params'     => $violationFormData,
                                'allow_redirects' => false,
                            ]);

                            // 检查响应状态并保存
                            $statusCode   = $response->getStatusCode();
                            $responseData = $response->getBody()->getContents();

                            // 检查响应状态并保存
                            if (200 === $statusCode) {
                                $this->info("车辆 {$contentItem['hphm']} 的违章查询成功！");
                            } else {
                                Log::channel('console')->warning("车辆 {$contentItem['hphm']} 的违章查询失败，状态码：{$statusCode}");
                            }

                            // 更新违章查询数据库记录
                            $violationRequest->update([
                                'or_status_code' => $statusCode,
                                'response'       => $responseData,
                            ]);

                            // 解析响应内容
                            $responseData = json_decode($responseData, true);

                            if (isset($responseData['data']['last'])) {
                                $isLastPage = $responseData['data']['last'];
                                $this->info("第 {$currentPage} 页的 last 值为：".($isLastPage ? 'true' : 'false'));
                            } else {
                                $this->error("无法解析第 {$currentPage} 页的 last 状态，假设为最后一页。");
                                Log::channel('console')->error("无法解析第 {$currentPage} 页的 last 状态。");
                                $isLastPage = true;
                            }

                            // 如果不是最后一页，准备请求下一页
                            if (!$isLastPage) {
                                ++$currentPage;
                            }
                        } catch (\Throwable $e) {
                            // 捕获异常并更新违章查询数据库记录
                            $violationRequest->update([
                                'response' => 'Error: '.$e->getMessage(),
                            ]);

                            $this->error("车辆 {$contentItem['hphm']} 的违章查询过程中发生错误：{$e->getMessage()}");
                            Log::channel('console')->error("车辆 {$contentItem['hphm']} 的违章查询过程中发生错误：{$e->getMessage()}");
                        }
                    }
                }
            }
        }
    }
}
