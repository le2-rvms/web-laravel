<?php

use Illuminate\Http\Request;

function str_render($info, $tpl): string
{
    return view('excel.'.$tpl, ['info' => json_decode($info, true)])->render();
}

function get_view_file(Request $request): string
{
    $action = $request->route()->getActionName(); // e.g. App\Http\Controllers\Admin\Auth\ProfileController@edit

    // 拆出类名与方法；兼容单动作控制器
    if (!str_contains($action, '@')) {
        $controllerFull = $action;
        $method         = '__invoke';
    } else {
        [$controllerFull, $method] = explode('@', $action);
    }

    $base = app()->getNamespace().'Http\Controllers\\';

    $baseQuoted = preg_quote($base, '#'); // <<< 关键：把命名空间前缀按 # 分隔符进行转义

    if ('__invoke' === $method) {
        $method = 'index'; // 你自己的映射规则
    }

    // 生成可用于 view() 的点号视图名：Admin.Auth.Profile.edit
    return preg_replace(
        [
            "#^{$baseQuoted}#", // 去掉命名空间前缀（已转义）
            '#Controller$#',          // 去掉 Controller 后缀
            '#\\\#',                 // 反斜杠 -> 点
        ],
        ['', '', '.'],
        $controllerFull
    ).'.'.$method;
    // 原来这里误写成又加了一次 .$method
}
