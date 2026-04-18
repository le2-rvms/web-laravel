<?php

namespace App\Http\Controllers\Admin\File;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public static Filesystem $drive;

    public function __construct()
    {
        // 默认使用 s3 磁盘（可对接 MinIO/S3）。
        static::$drive = Storage::disk('s3');
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public static function getDrive(): Filesystem
    {
        if (!isset(self::$drive)) {
            // 延迟初始化，避免静态属性未就绪。
            static::$drive = Storage::disk('s3');
        }

        return static::$drive;
    }

    public function index(Request $request)
    {
        $path = $request->query('path');

        // 列出目录与文件，返回大小与访问链接。
        $dir = static::$drive->directories($path);

        $files = [];
        foreach (static::$drive->files($path) as $filepath) {
            $files[] = [
                'filepath' => $filepath,
                'size'     => static::$drive->size($filepath),
                'url'      => static::$drive->url($filepath),
            ];
        }

        return $this->response()->withData(compact('dir', 'files'))->respond();
    }

    public function store(Request $request): Response
    {
        $input = $request->validate([
            'file' => 'required|file|max:10240', // 设置最大文件大小为 10MB
        ]);

        $file = $input['file'];

        $upload_path = $request->input('upload_path');

        $filename = $file->getClientOriginalName();

        // 按原文件名保存到指定目录。
        $filepath = static::$drive->putFileAs($upload_path, $file, $filename);

        //        $url = static::$drive->url($filepath);

        return $this->response()->withData([
            'filepath' => $filepath,
            //            'url'      => $url,
            //            'name'     => basename($filepath),
            //            'extname'  => pathinfo($filename, PATHINFO_EXTENSION),
        ])->respond();
    }

    public function show(string $id)
    {
        // 生成 minio 临时访问链接。
        $filePath = 'uploads/'.$filename;

        if (Storage::disk('minio')->exists($filePath)) {
            $url = Storage::disk('minio')->temporaryUrl($filePath, now()->addMinutes(5));

            return response()->json(['url' => $url]);
        }

        return response()->json(['error' => 'File not found'], 404);
    }

    public function update(Request $request, string $id) {}

    public function destroy(string $id)
    {
        $filePath = 'uploads/'.$filename;

        if (Storage::disk('minio')->exists($filePath)) {
            Storage::disk('minio')->delete($filePath);

            $this->response()->withMessages(['File deleted successfully']);

            return response()->json();
        }

        return response()->json(['error' => 'File not found'], 404);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
