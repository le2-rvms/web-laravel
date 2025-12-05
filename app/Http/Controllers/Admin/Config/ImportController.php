<?php

namespace App\Http\Controllers\Admin\Config;

use App\Attributes\ColumnDesc;
use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Config\ImportConfig;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\_\ImportTrait;
use App\Services\Uploader;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('导入')]
class ImportController extends Controller
{
    #[PermissionAction(PermissionAction::WRITE)]
    public function show(Request $request): Response
    {
        $this->response()->withExtras(
            ImportConfig::options(),
            ImportConfig::kv(),
        );

        return $this->response()->withData([])->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function template(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'model' => ['required', 'string', Rule::in(ImportConfig::keys())],
            ]
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle) {
                if (!$validator->failed()) {
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $model_name     = $input['model_name'];
        $model_basename = class_basename($input['model']);

        $ts       = now()->format('ymdHis');
        $filename = "template_{$model_basename}_{$ts}.xlsx";
        $path     = "tmp/{$filename}";

        /**
         * 根据配置生成带表头和说明的 Spreadsheet.
         */
        $buildSpreadsheet = function () use ($model_name) {
            /** @var ImportTrait $model_name */
            $ss = new Spreadsheet();

            $sheet = $ss->getActiveSheet();

            $ss
                ->getDefaultStyle()
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT)
            ;

            // columnDesc
            $columns = $model_name::importColumns();

            [$fieldAttributes,$fieldKeys,$fieldHeader,$modelDescArray] = static::parseField($columns);

            $index = 0;
            foreach ($columns as $column => $relation) {
                ++$index;

                list($field_model, $field_name) = $relation;

                $colLetter = Coordinate::stringFromColumnIndex($index);

                $sheet->setCellValue("{$colLetter}1", $header = $fieldAttributes[$column]);

                /** @var ColumnDesc $columnDesc */
                $columnDesc = data_get($modelDescArray, [$field_model, $field_name]);

                $sheet->setCellValue("{$colLetter}2", $description = (string) $columnDesc);

                // $sheet->getColumnDimension($colLetter)->setAutoSize(true);

                // 自动换行：超出宽度时分两行显示
                $sheet->getStyle("{$colLetter}1:{$colLetter}2")
                    ->getAlignment()
                    ->setWrapText(true)
                ;
                if ($columnDesc->required) {
                    $sheet->getStyle("{$colLetter}1:{$colLetter}2")->getFont()
                        ->getColor()
                        ->setRGB('FF0000')
                    ;
                }

                // 关闭自动宽度
                $sheet->getColumnDimension($colLetter)
                    ->setAutoSize(false)
                ;

                // 计算合适的列宽：
                $descLen   = ceil(mb_strlen($description, 'UTF-8') / 3);
                $headerLen = mb_strlen($header, 'UTF-8');
                $maxWidth  = $descLen > $headerLen ? $descLen : $headerLen;
                $sheet->getColumnDimension($colLetter)->setWidth($maxWidth + 10);
            }

            // 冻结前两行
            $sheet->freezePane('A3');

            return $ss;
        };
        $spreadsheet = $buildSpreadsheet();

        /**
         * 保存 Spreadsheet 到指定磁盘路径.
         */
        $saveSpreadsheet = function (Spreadsheet $ss, string $path) {
            $fullPath = Storage::disk('local')->path($path);
            (new Xlsx($ss))->save($fullPath);
        };
        $saveSpreadsheet($spreadsheet, $path);

        $result = temporarySignStorageAppTmp($path);

        return $this->response()->withData($result)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request): Response
    {
        // 验证上传文件
        $input = $request->validate(
            [
                'model_name' => ['required', Rule::in(ImportConfig::keys())],
            ] + Uploader::validator_rule_upload_object('import_file', true)
        );

        /** @var ImportTrait $model_name */
        $model_name = $input['model_name'];

        /** @var UploadedFile $import_file */
        $import_file = $input['import_file'];

        //        $filePath = $input_file->getRealPath();

        $fieldConfig = $model_name::importColumns();

        [$fieldAttributes,$fieldKeys,$fieldHeader,$modelDescArray] = static::parseField($fieldConfig);

        $fieldValidateBefore = $model_name::importBeforeValidateDo();

        $batchInsert = $model_name::importCreateDo();

        $fullPath = Storage::disk('local')->path($import_file['path_']);

        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        // 若只需第一个表，可启用以下两行
        // $sheetNames = $reader->listWorksheetNames($input_file->getRealPath());
        // $reader->setLoadSheetsOnly([$sheetNames[0]]);
        $spreadsheet = $reader->load($fullPath);

        // 读取数据并转换为关联数组

        [$headers, $datas] = collect($spreadsheet->getActiveSheet()->toArray())->partition(function ($value, $key) {
            return $key < 2;
        });
        $header = $headers->get(0);

        if ($fieldHeader !== $header) {
            throw new ClientException('表头不一致，请重新下载模板文件。');
        }

        if (0 === $datas->count()) {
            throw new ClientException('模板里没有内容。');
        }

        $datas = $datas
            ->filter(fn ($row) => array_filter($row)) // 跳过空行
            ->map(function (array $row) use ($model_name, &$count, $fieldAttributes, $fieldKeys, $fieldValidateBefore) {
                // trim
                $row = array_map('trim', $row);
                // 先组合成关联数组
                $item = array_combine($fieldKeys, $row);
                $item = array_filter($item);

                // 字段转换
                $fieldValidateBefore($item);

                // 执行验证
                $model_name::importValidatorRule($item, $fieldAttributes);
                //                $validator = Validator::make($item, $rules, [], $fieldAttributes);
                //                if ($validator->fails()) {
                //                    // a) 抛出异常，整个导入中断
                //                    throw new ValidationException($validator);
                //                    // b) 或者：把错误附加到 $item 里，后面 filter 掉
                //                    // $item['_errors'] = $validator->errors()->all();
                //                    // return $item;
                //                }

                ++$count;

                return $item;
            })
        ;

        // 批量检查
        $afterValidator = $model_name::importAfterValidatorDo();
        $afterValidator();

        // 事务，循环插入
        DB::transaction(function () use ($datas, $batchInsert) {
            $datas->each(function (array $data) use ($batchInsert) {
                $batchInsert($data);
            });
        });

        Storage::disk('local')->delete($import_file['path_']);

        $this->response()->withMessages(sprintf('成功导入%d条数据。', $count));

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::tmp($request, 'tmp', ['import_file'], $this);
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }

    private static function parseField(array $fieldConfig): array
    {
        // 1
        $column_lang       = [];
        $headers           = [];
        $column_desc_array = [];

        $lang_property = trans('property');

        foreach ($fieldConfig as $column => $relation) {
            list($relation_model, $relation_column) = $relation;

            $lang = data_get($lang_property, [class_basename($relation_model), $relation_column]);

            $column_lang[$column] = $lang;

            $headers[] = $lang;

            if (isset($column_desc_array[$relation_model])) {
                continue;
            }
            $column_desc_array[$relation_model] = static::parseColumnDesc($relation_model);
        }

        // result 2
        $fieldKeys = array_keys($fieldConfig);

        return [$column_lang, $fieldKeys, $headers, $column_desc_array];
    }

    private static function parseColumnDesc(string $model_name): ?array
    {
        $reflection = new \ReflectionClass($model_name);
        $attributes = $reflection->getAttributes(ColumnDesc::class);
        if (!$attributes) {
            return null;
        }

        $objs = [];
        foreach ($attributes as $attribute) {
            $obj = $attribute->newInstance();

            $objs[$obj->column] = $obj;
        }

        return $objs;
    }
}
