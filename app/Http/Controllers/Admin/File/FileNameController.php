<?php

namespace App\Http\Controllers\Admin\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileNameController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request) {}

    public function store(Request $request) {}

    public function show(string $id) {}

    public function update(Request $request, string $id)
    {
        $request->validate([
            'new_path' => 'required|string',
        ]);

        // 仅调整存储路径，不修改文件内容。
        // 将 uploads 下文件移动到新目录（仅变更路径）。
        $oldPath = 'uploads/'.$filename;
        $newPath = 'uploads/'.$request->input('new_path').'/'.$filename;

        if (!Storage::disk('minio')->exists($oldPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if (Storage::disk('minio')->move($oldPath, $newPath)) {
            $this->response()->withMessages(['File moved successfully']);

            return response()->json(['new_path' => $newPath]);
        }

        return response()->json(['error' => 'File move failed'], 500);
    }

    public function destroy(string $id) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
