<?php

namespace App\Http\Controllers\Admin\File;

use App\Attributes\PermissionNoneType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[PermissionNoneType]
class FileController
{
    private string $root = 'admin-files';

    public function index(Request $request): JsonResponse
    {
        $path = $this->normalizePath($request->string('path')->toString());
        $disk = Storage::disk('public');
        $target = $this->fullPath($path);

        $directories = array_map(function (string $directory) {
            return $this->trimRoot($directory);
        }, $disk->directories($target));

        $files = array_map(function (string $file) use ($disk) {
            return [
                'path' => $this->trimRoot($file),
                'filepath' => basename($file),
                'url' => $disk->url($file),
                'size' => $disk->size($file),
                'last_modified' => $disk->lastModified($file),
            ];
        }, $disk->files($target));

        return response()->json([
            'dir' => $directories,
            'files' => $files,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'upload_path' => ['nullable', 'string'],
        ]);

        $path = $this->normalizePath($request->string('upload_path')->toString());
        $disk = Storage::disk('public');
        $target = $this->fullPath($path);
        $filename = Str::uuid().'.'.$request->file('file')->getClientOriginalExtension();
        $stored = $request->file('file')->storeAs($target, $filename, 'public');

        return response()->json([
            'message' => '上传成功',
            'data' => [
                'filepath' => $this->trimRoot($stored),
                'url' => $disk->url($stored),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = $this->normalizePath($request->string('path')->toString());
        Storage::disk('public')->delete($this->fullPath($path));

        return response()->json([
            'message' => '删除成功',
        ]);
    }

    private function normalizePath(string $path): string
    {
        $normalized = trim(str_replace('..', '', $path), '/');

        return '/' === $path ? '' : $normalized;
    }

    private function fullPath(string $path): string
    {
        return trim($this->root.'/'.$path, '/');
    }

    private function trimRoot(string $path): string
    {
        return ltrim(str_replace($this->root, '', $path), '/');
    }
}
