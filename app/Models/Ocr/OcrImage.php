<?php

namespace App\Models\Ocr;

use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeDrivingLicenseRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeGeneralStructureRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeIdcardRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeVehicleLicenseRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use App\Attributes\ClassName;
use App\Enum\Customer\CuiGender;
use App\Models\_\ModelTrait;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[ClassName('OCR')]
/**
 * @property int         $oi_id       OCR识别序号
 * @property null|string $oi_file_md5 文件md5
 * @property null|string $oi_ocr_type 文件类型
 * @property null|mixed  $oi_result   OCR识别结果
 */
class OcrImage extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'oi_created_at';
    public const UPDATED_AT = 'oi_updated_at';
    public const UPDATED_BY = 'oi_updated_by';

    public const array types = [
        've_license_face_photo'     => 'RecognizeVehicleLicense', // 行驶证 ok
        've_license_back_photo'     => 'RecognizeVehicleLicense',
        'cui_id1_photo'             => 'recognizeIdcardWithOptions', // 身份证 ok
        'cui_id2_photo'             => 'recognizeIdcardWithOptions',
        'cui_driver_license1_photo' => 'recognizeDrivingLicenseWithOptions', // 驾驶证
    ];

    protected $primaryKey = 'oi_id';

    protected $guarded = ['oi_id'];

    protected $appends = [
    ];

    protected $casts = [
    ];

    public static function ocr($type, $md5Hash, $file): ?Model
    {
        $callback = self::types[$type] ?? null;
        if (!$callback) {
            return null;
        }

        $ocr = static::query()->where([
            'file_md5' => $md5Hash,
            'ocr_type' => $callback,
        ])->first();

        if ($ocr) {
            return $ocr;
        }

        $pathname = $file->getPathname();

        $result = call_user_func_array([static::class, $callback], [&$pathname]);

        $ocr = (new static([
            'file_md5' => $md5Hash,
            'ocr_type' => $callback,
            'result'   => $result->body->data,
        ]));

        $ocr->save();

        return $ocr;
    }

    public static function recognizeVehicleLicense($pathname)
    {
        $client = app(Ocrapi::class);

        $bodyStream = new Stream(fopen($pathname, 'r'));

        $Request = new RecognizeVehicleLicenseRequest([
            'body' => $bodyStream,
        ]);
        $runtime = new RuntimeOptions([]);

        try {
            // 复制代码运行请自行打印 API 的返回值
            return $client->recognizeVehicleLicenseWithOptions($Request, $runtime);
        } catch (\Exception $error) {
            if (!$error instanceof TeaError) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            Log::channel('aliyun')->error($error->message);

            throw $error;
        }
    }

    public static function recognizeIdcardWithOptions($pathname)
    {
        $client = app(Ocrapi::class);

        $bodyStream = new Stream(fopen($pathname, 'r'));

        $Request = new RecognizeIdcardRequest([
            'body' => $bodyStream,
        ]);
        $runtime = new RuntimeOptions([]);

        try {
            return $client->recognizeIdcardWithOptions($Request, $runtime);
        } catch (\Exception $error) {
            if (!$error instanceof TeaError) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }

            Log::channel('aliyun')->error($error->message);

            throw $error;
        }
    }

    public static function recognizeDrivingLicenseWithOptions($pathname)
    {
        $client = app(Ocrapi::class);

        $bodyStream = new Stream(fopen($pathname, 'r'));

        $Request = new RecognizeDrivingLicenseRequest([
            'body' => $bodyStream,
        ]);
        $runtime = new RuntimeOptions([]);

        try {
            return $client->recognizeDrivingLicenseWithOptions($Request, $runtime);
        } catch (\Exception $error) {
            if (!$error instanceof TeaError) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            Log::channel('aliyun')->error($error->message);

            throw $error;
        }
    }

    public static function recognizeGeneralStructureWithOptions($pathname)
    {
        $client = app(Ocrapi::class);

        $bodyStream = new Stream(fopen($pathname, 'r'));

        $Request = new RecognizeGeneralStructureRequest([
            'body' => $bodyStream,
        ]);
        $runtime = new RuntimeOptions([]);

        try {
            return $client->recognizeGeneralStructureWithOptions($Request, $runtime);
        } catch (\Exception $error) {
            if (!$error instanceof TeaError) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            Log::channel('aliyun')->error($error->message);

            throw $error;
        }
    }

    public static function indexQuery(): Builder
    {
        return DB::query();
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function result(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $data   = json_decode($value, true)['data'] ?? null;
                $return = [];
                if ($data) {
                    switch ($attributes['ocr_type']) {
                        case 'recognizeIdcardWithOptions':
                            $face_data = $data['face']['data'] ?? null;
                            if ($face_data) {
                                $return['cui_id_address']    = $face_data['address'];
                                $return['cui_id_number']     = $face_data['idNumber'];
                                $return['cui_name']          = $face_data['name'];
                                $return['cui_date_of_birth'] = str_replace(['年', '月', '日'], ['-', '-', ''], $face_data['birthDate']);
                                $return['cui_gender']        = array_flip(CuiGender::LABELS)[$face_data['sex']];
                            }

                            $back_data = $data['back']['data'] ?? null;
                            if ($back_data) {
                                if (preg_match('/-([0-9]{4})[.,]([0-9]{2})[.,]([0-9]{2})$/', $back_data['validPeriod'], $matches)) {
                                    // 组合为 'YYYY-MM-DD' 格式
                                    $year  = $matches[1];
                                    $month = $matches[2];
                                    $day   = $matches[3];

                                    $return['cui_id_expiry_date'] = "{$year}-{$month}-{$day}";
                                }
                            }

                            break;

                        case 'recognizeDrivingLicenseWithOptions':
                            $face_data = $data['face']['data'] ?? null;
                            if ($face_data) {
                                $return['cui_driver_license_number']   = $face_data['licenseNumber'];
                                $return['cui_driver_license_category'] = $face_data['approvedType'];
                                if (preg_match('/至(.+?)$/u', $face_data['validPeriod'], $matches)) {
                                    $return['cui_driver_license_expiry_date'] = $matches[1];
                                }
                            }

                            break;

                        case 'RecognizeVehicleLicense':
                            $face_data = $data['face']['data'] ?? null;
                            if ($face_data) {
                                $return['ve_license_address']       = $face_data['address'];
                                $return['ve_license_engine_no']     = $face_data['engineNumber'];
                                $return['ve_license_owner']         = $face_data['owner'];
                                $return['ve_license_purchase_date'] = $face_data['registrationDate'];
                                $return['ve_license_usage']         = $face_data['useNature'];
                                $return['ve_license_type']          = $face_data['vehicleType'];
                                $return['ve_license_vin_code']      = $face_data['vinCode'];
                            }

                            $back_data = $data['back']['data'] ?? null;
                            if ($back_data) {
                                if (preg_match('{(\d+)年(\d+)月}u', $back_data['inspectionRecord'], $matches)) {
                                    $return['ve_license_valid_until_date'] = "{$matches[1]}-{$matches[2]}-01";
                                }
                            }

                            break;
                    }
                }

                return $return;
            },
            set: function (string $value, array $attributes) {
                return $value;
            },
        );
    }
}
