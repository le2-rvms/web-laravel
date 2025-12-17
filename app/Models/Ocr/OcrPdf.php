<?php

namespace App\Models\Ocr;

use App\Models\_\ModelTrait;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class OcrPdf extends Model
{
    use ModelTrait;

    public const array types = [
        'compulsory_policy_file' => 'RecognizeCompulsory',
        'commercial_policy_file' => 'RecognizeCommercial',
    ];

    public static function extract($type, $md5Hash, UploadedFile $file): ?Model
    {
        $callback = self::types[$type] ?? null;
        if (!$callback) {
            return null;
        }

        //        $pathname = $file->getPathname();

        $tempPath = $file->store('share');

        // 调用转换服务
        $text = (function () use ($tempPath) {
            $client   = new Client();
            $response = $client->post('http://poppler-api:8080/execute', [
                'json' => [
                    'cmd'  => 'pdftotext',
                    'args' => [
                        './data/'.basename($tempPath),
                        '-',
                    ],
                ],
            ]);

            return (string) $response->getBody();
        })();

        $result = call_user_func_array([static::class, $callback], [&$text]);

        return new static([
            'file_md5' => $md5Hash,
            'ocr_type' => $callback,
            'result'   => $result,
        ]);
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query();
    }

    public static function RecognizeCompulsory($text)
    {
        $companies = [
            [
                '中国太平洋财产保险股份有限公司',
                [
                    // 车牌号：匹配“号 牌 号 码”后面的字符（支持中文、字母、数字）
                    'vi_compulsory_plate_no' => '/号\s*牌\s*号\s*码\s*([\x{4e00}-\x{9fa5}A-Z0-9]+)/u',

                    // 车架号：匹配“识别代码”后面连续17位字母或数字（不区分大小写）
                    'vi_vin' => '/识别代码.*?([A-Z0-9]{17})/i',

                    // 保单号：匹配“保险单号：”后面的字母和数字
                    'vi_compulsory_policy_number' => '/保险单号[:：]\s*([A-Z0-9]+)/iu',

                    // 保险公司：匹配“公司名称：”后面直到换行的内容
                    'vi_compulsory_insurance_company' => '/公司名称[:：]\s*([^\n]+)/u',

                    // 保费金额：匹配“保险费合计”部分中“￥：”后面的金额（包含小数点）
                    'vi_compulsory_premium' => '/保险费合计.*?￥[:：]\s*([\d\.]+)元/u',

                    // 开始日期：匹配“保险期间自”后面的日期（形如 “2024年12月17日”）
                    'vi_compulsory_start_date' => '/保险期间自\s*(\d{4}年\d{1,2}月\d{1,2}日)/u',

                    // 结束日期：匹配“至”后面的日期（形如 “2025年12月17日”）
                    'vi_compulsory_end_date' => '/至\s*(\d{4}年\d{1,2}月\d{1,2}日)/u',
                ],
                [
                    // 将中文日期格式转换为用中线分隔的格式，例如 "2024年12月17日" 转为 "2024-12-17"
                    'vi_compulsory_start_date' => function ($date) {
                        if (!$date) {
                            return $date;
                        }

                        return str_replace(['年', '月', '日'], ['-', '-', ''], $date);
                    },
                    'vi_compulsory_end_date' => function ($date) {
                        if (!$date) {
                            return $date;
                        }

                        return str_replace(['年', '月', '日'], ['-', '-', ''], $date);
                    },
                    // 如果后续有其他字段需要转换，也可以在此处添加匿名函数
                ],
            ],
        ];

        foreach ($companies as $company) {
            list($name, $patterns, $converters) = $company;
            $preg_match_result                  = preg_match("/{$name}/u", $text);
            if (!$preg_match_result) {
                continue;
            }
            // 根据正则表达式提取字段
            $results = [];
            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $results[$key] = $matches[1];
                } else {
                    $results[$key] = null;
                }
            }

            // --------------------
            // 遍历匿名函数数组，对对应字段进行后处理
            foreach ($converters as $key => $converter) {
                if (isset($results[$key])) {
                    $results[$key] = $converter($results[$key]);
                }
            }

            return $results;
        }

        return null;
    }

    public static function RecognizeCommercial($text)
    {
        $companies = [
            [
                '中国太平洋财产保险股份有限公司',
                [
                    // 车牌号：匹配“号牌号码：”后面的内容（直到换行）
                    'vi_commercial_plate_no' => '/号牌号码[:：]\s*([^\n]+)/u',

                    // 车架号：匹配“VIN码/车架号：”后面连续 17 位字母或数字（不区分大小写）
                    'vi_vin' => '/VIN码\/车架号[:：]\s*([A-Z0-9]{17})/iu',

                    // 保单号：匹配“保险单号：”后面的字母和数字
                    'vi_commercial_policy_number' => '/保险单号[:：]\s*([A-Z0-9]+)/iu',

                    // 保险公司：匹配“公司名称：”后面直到换行的内容
                    'vi_commercial_insurance_company' => '/公司名称[:：]\s*([^\n]+)/u',

                    // 保费金额：匹配“￥：”后面的金额（数字和小数点）
                    'vi_commercial_premium' => '/￥[:：]\s*([\d\.]+)\s*元/u',

                    // 开始日期：匹配“保险期间：”后面的日期，形如 “2024 年 12 月 17 日”
                    'vi_commercial_start_date' => '/保险期间[:：]\s*(\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日)/u',

                    // 结束日期：匹配“至”后面的日期，形如 “2025 年 12 月 17 日”
                    'vi_commercial_end_date' => '/至\s*(\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日)/u',
                ],
                [
                    // 将中文日期格式转换为用中线分隔的格式，例如 "2024年12月17日" 转为 "2024-12-17"
                    'vi_commercial_start_date' => function ($date) {
                        if (!$date) {
                            return $date;
                        }

                        return preg_replace('/\s+/u', '', str_replace(['年', '月', '日', ' '], ['-', '-', '', ''], $date));
                    },
                    'vi_commercial_end_date' => function ($date) {
                        if (!$date) {
                            return $date;
                        }

                        return preg_replace('/\s+/u', '', str_replace(['年', '月', '日', ' '], ['-', '-', '', ''], $date));
                    },
                    // 如果后续有其他字段需要转换，也可以在此处添加匿名函数
                ],
            ],
        ];

        foreach ($companies as $company) {
            list($name, $patterns, $converters) = $company;
            $preg_match_result                  = preg_match("/{$name}/u", $text);
            if (!$preg_match_result) {
                continue;
            }

            // 根据正则表达式提取字段
            $results = [];
            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $results[$key] = $matches[1];
                } else {
                    $results[$key] = null;
                }
            }

            // --------------------
            // 遍历匿名函数数组，对对应字段进行后处理
            foreach ($converters as $key => $converter) {
                if (isset($results[$key])) {
                    $results[$key] = $converter($results[$key]);
                }
            }

            return $results;
        }

        return null;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
