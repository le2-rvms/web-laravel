<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PageExcel
{
    public function __construct(protected string $action_name) {}

    public static function check_request($request): bool
    {
        return 'excel' === $request->output;
    }

    public function export(Builder $query, array $columns): array
    {
        [$class,$action] = explode('@', $this->action_name);
        $controllerClass = preg_replace('{Controller$}', '', class_basename($class));

        $filename = trans('controller.'.$controllerClass).trans('app.actions.'.$action).'_'.uniqid().'.xlsx';

        // 初始化 Spreadsheet
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        $properties = trans('property');

        $columnIndex = 1;
        foreach ($columns as $column_title => $column_value) {
            $cell_address = Coordinate::stringFromColumnIndex($columnIndex).'1';
            $cell_value   = data_get($properties, $column_title);
            $sheet->setCellValue($cell_address, $cell_value);
            ++$columnIndex;
        }

        // 冻结首行并设置样式
        $sheet->freezePane('A2');
        //        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($columns)).'1')->getFont()->setBold(true);

        // 分块查询并写入数据
        $row = 2;
        $query->chunk(500, function ($items) use ($columns, $sheet, &$row) {
            //            $values($sheet, $items, $row);
            foreach ($items as $item) {
                $columnIndex = 1;
                foreach ($columns as $column_title => $column_value) {
                    $cell = Coordinate::stringFromColumnIndex($columnIndex++).$row;
                    $sheet->setCellValue($cell, $column_value($item));
                }

                ++$row;
            }
        });

        // 自动调整列宽（在所有数据写入后执行）
        foreach (range(1, count($columns)) as $colIdx) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))
                ->setAutoSize(true)
            ;
        }

        // 写入输出流
        $writer = new Xlsx($spreadsheet);

        $path = 'share/'.$filename;

        $diskLocal = Storage::disk('local');

        $absPath = $diskLocal->path($path);

        $writer->save($absPath);

        return temporarySignStorageAppShare($absPath);
    }
}
