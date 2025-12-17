<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// if (!function_exists('safeGet')) {
//    function safeGet(mixed $object, string $key, mixed $default = null): mixed
//    {
//        // is array
//        if (is_array($object)) {
//            if (isset($object[$key])) {
//                $default = $object[$key];
//            }
//        }
//        // is object ...
//
//        // is interface ...
//        return $default;
//    }
// }
// if (!function_exists('read_csv')) {
//    function read_csv($filename): array
//    {
//        $rows = [];
//        if (($handle = fopen($filename, 'r')) !== false) {
//            while (($data = fgetcsv($handle)) !== false) {
//                // convert file code shift-jis to utf-8
//                $rows[] = mb_convert_encoding($data, 'UTF-8', 'SJIS-win');
//            }
//            fclose($handle);
//        }
//
//        return $rows;
//    }
// }

// if (!function_exists('replace_error')) {
//    function replace_error($subject, $prefix, $regex, $num = 1): null|array|string
//    {
//        $str = preg_replace_callback($regex, function ($matches) use ($prefix, $num) {
//            $num = intval($matches[1]) + $num;
//
//            return sprintf($prefix, $num);
//        }, $subject, 1);
//        if (!is_string($str)) {
//            return $subject;
//        }
//
//        return $str;
//    }
// }

// if (!function_exists('get_ctrl')) {
//    function get_ctrl($controller = false): string
//    {
//        $ctrl = class_basename(request()->route()->getController());
//
//        return $controller ? $ctrl : str_replace('Controller', '', $ctrl);
//    }
// }

// if (!function_exists('get_act')) {
//    function get_act($controller = false): string
//    {
//        return class_basename(request()->route()->getActionMethod());
//    }
// }

// if (!function_exists('str_starts_with')) {
//    function str_starts_with($haystack, $needle)
//    {
//        return '' !== (string) $needle && 0 === strncmp($haystack, $needle, strlen($needle));
//    }
// }

// if (!function_exists('str_end_with')) {
//    function str_end_with($haystack, $needle): bool
//    {
//        return '' !== $needle && substr($haystack, -strlen($needle)) === (string) $needle;
//    }
// }

// if (!function_exists('egr_comment')) {
//    function egr_comment(string $str)
//    {
//        return preg_replace_callback_array(
//            [
//                '{<!--.+?-->}ims' => function ($matches) {
//                    return '';
//                },
//                //                '{([\s|,|;|\{\)]+)//.+?(\n)}i' => function ($matches) {
//                //                    return $matches[1].$matches[2];
//                //                },
//                '{^//.+?(\n)}i' => function ($matches) {
//                    return $matches[1];
//                },
//                '{/\*\*.+?\*/}is' => function ($matches) {
//                    return '';
//                },
//                '{/\*.+?\*/}is' => function ($matches) {
//                    return '';
//                },
//            ],
//            $str
//        );
//    }
// }

// if (!function_exists('combine_arrays')) {
//    function combine_arrays($keys, $values)
//    {
//        $count        = count($keys);
//        $slicedValues = array_slice($values, 0, $count);
//        $paddedValues = array_pad($slicedValues, $count, '');
//
//        return array_combine($keys, $paddedValues);
//    }
// }

// if (!function_exists('num_rtrim')) {
//    function num_rtrim($num)
//    {
//        return rtrim(preg_replace('/(\.\d*?[1-9]*?)0+$/', '$1', $num), '.');
//    }
// }

function temporarySignStorageAppTmp(string $filename): array
{
    $url = URL::temporarySignedRoute(
        'api-admin.storage.tmp',                // 路由名称
        $expiration = Carbon::now()->addMinutes(30),  // 过期时间
        ['filename' => basename($filename)]       // 路由参数
    );

    $expiration = $expiration->toDateTimeString();

    return compact('url', 'expiration');
}

function temporarySignStorageAppShare(string $filename): array
{
    $url = URL::temporarySignedRoute(
        'api-admin.storage.share',                // 路由名称
        $expiration = Carbon::now()->addMinutes(30),  // 过期时间
        ['filename' => basename($filename)]       // 路由参数
    );

    $expiration = $expiration->toDateTimeString();

    return compact('url', 'expiration');
}

function trans_property($class): array
{
    return trans('property.'.class_basename($class));
}

function trans_model($class): string
{
    return trans('model.'.class_basename($class).'.name');
}

function trans_model_suffix($class): string
{
    return trans('model.'.class_basename($class).'.suffix');
}

function get_field_name($class): string
{
    $class_basename = class_basename($class);

    $snake  = Str::snake($class_basename);
    $prefix = strstr($snake, '_', true) ?: $snake;

    $snake = preg_replace('/__.+$/', '', $snake);  // 去掉后缀

    return $prefix.'.'.$snake;
}

function get_field_short_name($class): string
{
    $class_basename = class_basename($class);

    $snake = Str::snake($class_basename);

    return preg_replace('/__.+$/', '', $snake); // 去掉后缀
}
