<?php

use App\Exceptions\ServerException;
use Illuminate\Support\Str;

// if (!function_exists('abort_if_forbidden')) {
//    function abort_if_forbidden(string $permission, $message = 'You have not permission to this page!'): void
//    {
//        abort_if(is_null(auth()->user()) || !auth()->user()->can($permission), 403, $message);
//    }
// }

// if (!function_exists('message_set')) {
//    function message_set($message, $type)
//    {
//        session()->put('_message', $message);
//        session()->put('_type', $type);
//    }
// }

// if (!function_exists('error_message')) {
//    function error_message($message)
//    {
//        message_set($message, 'bg-danger');
//    }
// }

// if (!function_exists('success_message')) {
//    function success_message($message)
//    {
//        message_set($message, 'bg-success');
//    }
// }

// if (!function_exists('warning_message')) {
//    function warning_message($message)
//    {
//        message_set($message, 'bg-warning');
//    }
// }

// if (!function_exists('info_message')) {
//    function info_message($message)
//    {
//        message_set($message, 'bg-info', 5);
//    }
// }

// if (!function_exists('message_clear')) {
//    function message_clear()
//    {
//        session()->pull('_message');
//        session()->pull('_type');
//    }
// }

function message_success(string $action): array
{
    $message = trans_method($action);
    $message = $message.'完成';

    return [$message, 'bg-success'];
}

function trans_method(array|string $method_full): string
{
    if (is_string($method_full)) {
        [$controller, $method] = explode('::', $method_full);

        $controller_short = preg_replace('{Controller$}', '', class_basename($controller));
    } elseif (is_array($method_full)) {
        [$model, $method] = $method_full;

        $model = Str::studly($model);
    }

    $controller_trans = trans(sprintf('controller.%s', $controller_short));

    if (preg_match('/^[a-z]/', $controller_trans)) {
        throw new ServerException($controller_trans);
    }

    $method_trans = trans('app.actions.'.$method);

    return $controller_trans.$method_trans;
}

function trans_controller(array|string $controller): string
{
    $controller_trans = trans('app.controllers.'.$controller);

    return $controller_trans ?? $controller;
}
