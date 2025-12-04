<?php

namespace App\Services;

use App\Enum\Sale\DtDtExportType;
use App\Enum\Sale\DtDtTypeMacroChars;
use App\Exceptions\ServerException;
use App\Models\Sale\DocTpl;
use GuzzleHttp\Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class DocTplService
{
    private Filesystem $diskS3;

    private array $tempFiles = [];
    private Filesystem $diskLocal;

    public function __construct()
    {
        $this->diskS3 = Storage::disk('s3');

        $this->diskLocal = Storage::disk('local');

        $this->registerTempFileCleanup();
    }

    /**
     * @see https://help.libreoffice.org/latest/en-US/text/shared/guide/pdf_params.html
     */
    public function GenerateDoc(DocTpl $docTpl, string $mode, ?Model $model = null): array
    {
        $tempDocx = (function () use ($docTpl) {
            // 从 MinIO 获取模板文件流
            $filepath = $docTpl->dt_file['path_'];
            $stream   = $this->diskS3->readStream($filepath);
            if (!$stream) {
                abort(404, '模板文件不存在');
            }

            // 将流写入本地 tmp DOCX 文件
            $tmpPath = 'share/'.uniqid().'_'.mt_rand().'.docx';

            $_ = $this->diskLocal->put($tmpPath, $stream);

            fclose($stream);

            $tempDocxPathFull = $this->diskLocal->path($tmpPath);

            $this->addTempFile($tempDocxPathFull);

            return $tempDocxPathFull;
        })();

        $tempFilledDocx = (function () use ($model, $docTpl, $tempDocx) {
            // 替换模板占位符并保存到新的 DOCX 文件
            // https://phpword.readthedocs.io/en/latest/templates-processing.html
            $tpl = new TemplateProcessor($tempDocx);
            $tpl->setMacroChars(DtDtTypeMacroChars::Opening->value, DtDtTypeMacroChars::Closing->value);

            $dt_type = $docTpl->dt_type;
            //            $rule = array_map(function ($value) {
            //                return '&#9608;';
            //            }, $rule);

            $fieldsAndRelations = $dt_type->getFieldsAndRelations(valueOnly: true);

            $fieldsAndRelationsDot = Arr::dot($fieldsAndRelations);

            if ($model) {
                $model_data = $model->toArray();

                foreach ($fieldsAndRelationsDot as $rule_key => $rule_label) {
                    if (str_ends_with($rule_key, '_func')) { // todo  当不是订单的时候，会因为 sale_order.payments_phpword_fun 调用报错
                        $model->{$rule_key}($tpl, $rule_label);

                        continue;
                    }

                    $rule_value = data_get($model_data, $rule_key);

                    if (null === $rule_label || is_array($rule_value) || is_object($rule_value)) {
                        continue;
                    }

                    $tpl->setValue($rule_label, $rule_value);
                }
            } else {
                foreach ($fieldsAndRelationsDot as $rule_key => $rule_label) {
                    $rule_value = '&#9608;';
                    if ('' === $rule_label) {
                        continue;
                    }
                    $tpl->setValue($rule_label, $rule_value);
                }
            }

            //            $tpl->setValues($rule);
            //            $tpl->setValue('哈哈哈', date('Y-m-d'));

            $path = 'share/'.uniqid().'_'.mt_rand().'.docx';

            $tempPathDocxFull = $this->diskLocal->path($path);

            $tpl->saveAs($tempPathDocxFull);

            return $tempPathDocxFull;
        })();

        // 未生成 PDF时直接上传 DOCX 文件
        if (DtDtExportType::DOCX === $mode) {
            return temporarySignStorageAppShare($tempFilledDocx);
        }

        $pdfTemp = (function () use ($tempFilledDocx) {
            $this->addTempFile($tempFilledDocx);

            // 3. 调用转换服务
            //            try {
            $client   = new Client();
            $response = $client->post('http://libreoffice-api:8080/execute', [
                'json' => [
                    'cmd'  => 'soffice',
                    'args' => [
                        '--headless', '--convert-to', 'pdf:writer_pdf_Export', '--outdir', './data', './data/'.basename($tempFilledDocx),
                    ],
                ],
            ]);
            $result = (string) $response->getBody();

            $pdfTemp = preg_replace('/\.docx$/i', '.pdf', $tempFilledDocx);
            if (!file_exists($pdfTemp)) {
                throw new ServerException('PDF 文件未生成');
            }

            return $pdfTemp;
        })();

        return temporarySignStorageAppShare($pdfTemp);
    }

    /**
     * 添加临时文件到清理列表.
     */
    private function addTempFile(string $file): void
    {
        $this->tempFiles[] = $file;
    }

    /**
     * 注册进程退出时删除所有临时文件.
     */
    private function registerTempFileCleanup(): void
    {
        register_shutdown_function(function () {
            foreach ($this->tempFiles as $file) {
                if ($file && file_exists($file)) {
                    //                    @unlink($file);
                }
            }
        });
    }
}
