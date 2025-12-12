<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Models\Ocr\OcrImage;
use App\Models\Ocr\OcrPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class Uploader
{
    public static function upload(Request $request, $fileDic, array $allow_field_name, Controller $controller): JsonResponse
    {
        $input = $request->validate([
            'file'       => ['required', 'file', 'max:'.(1024 * 20)],
            'field_name' => ['required', Rule::in($allow_field_name)],
        ]);

        $filename = $input['file']->getClientOriginalName();

        $md5Hash = md5_file($input['file']->getRealPath());

        $upload_path = $fileDic.'/'.$input['field_name'].'/'.date('Y-m').'/'.substr($md5Hash, 0, 2).'/'.$md5Hash;

        $filepath = Storage::disk('s3')->putFileAs($upload_path, $input['file'], $filename);

        if ('application/pdf' === $input['file']->getClientMimeType()) {
            $ocr = OcrPdf::extract($input['field_name'], $md5Hash, $input['file']);
        } else {
            $ocr = OcrImage::ocr($input['field_name'], $md5Hash, $input['file']);
        }

        return $controller->response()->withData([
            'filepath' => $filepath,
            'ocr'      => $ocr,
        ])->respond();
    }

    public static function tmp(Request $request, $fileDic, $allow_field_name, Controller $controller): JsonResponse
    {
        $input = $request->validate([
            'file'       => ['required', 'file', 'max:'.(1024 * 20)],
            'field_name' => ['required', Rule::in($allow_field_name)],
        ]);

        $filename = $input['file']->getClientOriginalName();

        $md5Hash = md5_file($input['file']->getRealPath());

        $upload_path = $fileDic.'/'.$input['field_name'].'/'.date('Y-m').'/'.substr($md5Hash, 0, 2).'/'.$md5Hash;

        $filepath = Storage::disk('local')->putFileAs($upload_path, $input['file'], $filename);

        return $controller->response()->withData([
            'filepath' => $filepath,
        ])->respond();
    }

    public static function validator_rule_upload_array($prefix): array
    {
        return [
            $prefix              => ['bail', 'nullable', 'array'],
            $prefix.'.*.name'    => ['bail', 'required', 'string'],
            $prefix.'.*.extname' => ['bail', 'required', 'string'],
            $prefix.'.*.path_'   => ['bail', 'required', 'string'],
            $prefix.'.*.size'    => ['bail', 'required', 'int'],
        ];
    }

    public static function validator_rule_upload_object($prefix, $required = false): array
    {
        return [
            $prefix            => ['bail', $required ? 'required' : 'nullable', 'array'],
            $prefix.'.name'    => ['bail', 'required_with:'.$prefix, 'string'],
            $prefix.'.extname' => ['bail', 'required_with:'.$prefix, 'string'],
            $prefix.'.path_'   => ['bail', 'required_with:'.$prefix, 'string'],
            $prefix.'.size'    => ['bail', 'required_with:'.$prefix, 'int'],
        ];
    }
}
