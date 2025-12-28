<?php

namespace App\Http\Controllers\Admin\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function downloadShare(Request $request, $filename): BinaryFileResponse
    {
        // Laravel 的 signed 中间件已校验签名并处理过期。
        // 如需在方法内再次校验，可用：
        if (!$request->hasValidSignature()) {
            abort(403, '无效或已过期的下载链接。');
        }

        $diskLocal = Storage::disk('local');

        $tmpPath = 'share/'.$filename;

        if (!$diskLocal->exists($tmpPath)) {
            abort(404, '文件不存在');
        }

        // 拿到绝对路径
        $tmpPathFull = $diskLocal->path($tmpPath);

        // 返回下载响应，并在发送完毕后删除临时文件
        return response()
            ->file($tmpPathFull)
            ->deleteFileAfterSend(true)
        ;
    }

    public function downloadTmp(Request $request, $filename): BinaryFileResponse
    {
        // Laravel 的 signed 中间件已校验签名并处理过期。
        // 如需在方法内再次校验，可用：
        if (!$request->hasValidSignature()) {
            abort(403, '无效或已过期的下载链接。');
        }

        $diskLocal = Storage::disk('local');

        $tmpPath = 'tmp/'.$filename;

        if (!$diskLocal->exists($tmpPath)) {
            abort(404, '文件不存在');
        }

        // 拿到绝对路径
        $tmpPathFull = $diskLocal->path($tmpPath);

        // 返回下载响应，并在发送完毕后删除临时文件
        return response()
            ->file($tmpPathFull)
            ->deleteFileAfterSend(true)
        ;
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
