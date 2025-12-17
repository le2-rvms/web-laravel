<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\VehicleInspection\ViIsCompanyBorne;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[ClassName('车辆保险单', '信息')]
#[ColumnDesc('vi_id')]
#[ColumnDesc('vi_ve_id', required: true)]
#[ColumnDesc('vi_plate_no', required: true)]
#[ColumnDesc('vi_compulsory_policy_file')]
#[ColumnDesc('vi_compulsory_policy_photos')]
#[ColumnDesc('vi_compulsory_policy_addendum_file')]
#[ColumnDesc('vi_compulsory_plate_no')]
#[ColumnDesc('vi_compulsory_policy_number')]
#[ColumnDesc('vi_compulsory_start_date', type: ColumnType::DATE)]
#[ColumnDesc('vi_compulsory_end_date', type: ColumnType::DATE)]
#[ColumnDesc('vi_compulsory_premium')]
#[ColumnDesc('vi_compulsory_insured_company')]
#[ColumnDesc('vi_compulsory_org_code')]
#[ColumnDesc('vi_compulsory_insurance_company')]
#[ColumnDesc('vi_carrier_liability_policy_file')]
#[ColumnDesc('vi_carrier_liability_policy_photos')]
#[ColumnDesc('vi_carrier_liability_plate_no')]
#[ColumnDesc('vi_carrier_liability_policy_number')]
#[ColumnDesc('vi_carrier_liability_start_date', type: ColumnType::DATE)]
#[ColumnDesc('vi_carrier_liability_end_date', type: ColumnType::DATE)]
#[ColumnDesc('vi_carrier_liability_premium')]
#[ColumnDesc('vi_carrier_liability_insured_company')]
#[ColumnDesc('vi_carrier_liability_org_code')]
#[ColumnDesc('vi_carrier_liability_insurance_company')]
#[ColumnDesc('vi_commercial_policy_file')]
#[ColumnDesc('vi_commercial_policy_photos')]
#[ColumnDesc('vi_commercial_policy_addendum_file')]
#[ColumnDesc('vi_commercial_plate_no')]
#[ColumnDesc('vi_commercial_policy_number')]
#[ColumnDesc('vi_commercial_start_date', type: ColumnType::DATE)]
#[ColumnDesc('vi_commercial_end_date', type: ColumnType::DATE)]
#[ColumnDesc('vi_commercial_premium')]
#[ColumnDesc('vi_commercial_insured_company')]
#[ColumnDesc('vi_commercial_org_code')]
#[ColumnDesc('vi_commercial_insurance_company')]
#[ColumnDesc('vi_is_company_borne', desc: '输入0、1')]
#[ColumnDesc('vi_remark')]
/**
 * @property int         $vi_id                                  保险单序号
 * @property int         $vi_ve_id                               车辆序号
 * @property null|mixed  $vi_compulsory_policy_file              交强险保单文件路径
 * @property null|mixed  $vi_compulsory_policy_photos            交强险保单照片路径
 * @property null|mixed  $vi_compulsory_policy_addendum_file     交强险批单文件路径
 * @property null|string $vi_compulsory_plate_no
 * @property null|string $vi_compulsory_policy_number            交强险保单号
 * @property null|Carbon $vi_compulsory_start_date               交强险开始日期
 * @property null|Carbon $vi_compulsory_end_date                 交强险结束日期
 * @property null|float  $vi_compulsory_premium                  交强险保费
 * @property null|string $vi_compulsory_insured_company          交强险被保险公司
 * @property null|string $vi_compulsory_org_code                 交强险组织机构代码
 * @property null|string $vi_compulsory_insurance_company        交强险保险公司
 * @property null|mixed  $vi_carrier_liability_policy_file       承运人责任险保单文件路径
 * @property null|mixed  $vi_carrier_liability_policy_photos     承运人责任险保单照片路径
 * @property null|string $vi_carrier_liability_plate_no
 * @property null|string $vi_carrier_liability_policy_number     承运人责任险保单号
 * @property null|Carbon $vi_carrier_liability_start_date        承运人责任险开始日期
 * @property null|Carbon $vi_carrier_liability_end_date          承运人责任险结束日期
 * @property null|float  $vi_carrier_liability_premium           承运人责任险保费
 * @property null|string $vi_carrier_liability_insured_company   承运人责任险被保险公司
 * @property null|string $vi_carrier_liability_org_code          承运人责任险组织机构代码
 * @property null|string $vi_carrier_liability_insurance_company 承运人责任险保险公司
 * @property null|mixed  $vi_commercial_policy_file              商业险保单文件路径
 * @property null|mixed  $vi_commercial_policy_photos            商业险保单照片路径
 * @property null|mixed  $vi_commercial_policy_addendum_file     商业险批单文件路径
 * @property null|string $vi_commercial_plate_no
 * @property null|string $vi_commercial_policy_number            商业险保单号
 * @property null|Carbon $vi_commercial_start_date               商业险开始日期
 * @property null|Carbon $vi_commercial_end_date                 商业险结束日期
 * @property null|float  $vi_commercial_premium                  商业险保费
 * @property null|string $vi_commercial_insured_company          商业险被保险公司
 * @property null|string $vi_commercial_org_code                 商业险组织机构代码
 * @property null|string $vi_commercial_insurance_company        商业险保险公司
 * @property null|int    $vi_is_company_borne                    是否公司承担;1表示是，0表示否
 * @property null|string $vi_remark                              保险单备注
 *
 * -- relation
 * @property Vehicle $Vehicle
 */
class VehicleInsurance extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'vi_created_at';
    public const UPDATED_AT = 'vi_updated_at';
    public const UPDATED_BY = 'vi_updated_by';

    protected $primaryKey = 'vi_id';

    protected $guarded = ['vi_id'];

    protected $casts = [
        'vi_is_company_borne' => ViIsCompanyBorne::class,
    ];

    protected $appends = [];

    protected $attributes = [];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vi_ve_id', 've_id')->with('VehicleModel');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $vi_ve_id = $search['vi_ve_id'] ?? null;

        return DB::query()
            ->from('vehicle_insurances', 'vi')
            ->when(null === $vi_ve_id, function (Builder $query) {
                return $query->joinSub(
                    // 直接在 joinSub 中定义子查询
                    DB::table('vehicle_insurances')
                        ->select('vi_ve_id', DB::raw('MAX(vi_compulsory_start_date) as max_vi_compulsory_start_date'))
                        ->groupBy('vi_ve_id'),
                    'p2',
                    function ($join) {
                        $join->on('vi.vi_ve_id', '=', 'p2.vi_ve_id')
                            ->on('vi.vi_compulsory_start_date', '=', 'p2.max_vi_compulsory_start_date')
                        ;
                    }
                );
            })
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vi.vi_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->when($vi_ve_id, function (Builder $query) use ($vi_ve_id) {
                $query->where('vi.vi_ve_id', '=', $vi_ve_id);
            })
            ->when(
                null === $vi_ve_id,
                function (Builder $query) {
                    $query->orderByDesc('vi.vi_id');
                },
                function (Builder $query) {
                    $query->orderBy('vi.vi_id');
                }
            )
            ->select('vi.*', 've.ve_plate_no', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->when(null === $vi_ve_id, function (Builder $query) {
                $query->addSelect(DB::raw('CAST(EXTRACT(EPOCH FROM now() - vi.vi_compulsory_end_date) / 86400.0 AS integer ) as vi_compulsory_interval_day'));
                $query->addSelect(DB::raw('CAST(EXTRACT(EPOCH FROM now() - vi.vi_commercial_end_date) / 86400.0 AS integer ) as vi_commercial_interval_day'));
                $query->addSelect(DB::raw('CAST(EXTRACT(EPOCH FROM now() - vi.vi_carrier_liability_end_date) / 86400.0 AS integer ) as vi_carrier_liability_interval_day'));
            })
        ;
    }

    public static function importColumns(): array
    {
        return [
            'plate_no' => [VehicleInsurance::class, 'plate_no'],
            //                'compulsory_plate_no',
            'compulsory_policy_number'     => [VehicleInsurance::class, 'compulsory_policy_number'],
            'compulsory_start_date'        => [VehicleInsurance::class, 'compulsory_start_date'],
            'compulsory_end_date'          => [VehicleInsurance::class, 'compulsory_end_date'],
            'compulsory_premium'           => [VehicleInsurance::class, 'compulsory_premium'],
            'compulsory_insured_company'   => [VehicleInsurance::class, 'compulsory_insured_company'],
            'compulsory_org_code'          => [VehicleInsurance::class, 'compulsory_org_code'],
            'compulsory_insurance_company' => [VehicleInsurance::class, 'compulsory_insurance_company'],
            //                'carrier_liability_plate_no',
            'carrier_liability_policy_number'     => [VehicleInsurance::class, 'carrier_liability_policy_number'],
            'carrier_liability_start_date'        => [VehicleInsurance::class, 'carrier_liability_start_date'],
            'carrier_liability_end_date'          => [VehicleInsurance::class, 'carrier_liability_end_date'],
            'carrier_liability_premium'           => [VehicleInsurance::class, 'carrier_liability_premium'],
            'carrier_liability_insured_company'   => [VehicleInsurance::class, 'carrier_liability_insured_company'],
            'carrier_liability_org_code'          => [VehicleInsurance::class, 'carrier_liability_org_code'],
            'carrier_liability_insurance_company' => [VehicleInsurance::class, 'carrier_liability_insurance_company'],
            //                'commercial_plate_no',
            'commercial_policy_number'     => [VehicleInsurance::class, 'commercial_policy_number'],
            'commercial_start_date'        => [VehicleInsurance::class, 'commercial_start_date'],
            'commercial_end_date'          => [VehicleInsurance::class, 'commercial_end_date'],
            'commercial_premium'           => [VehicleInsurance::class, 'commercial_premium'],
            'commercial_insured_company'   => [VehicleInsurance::class, 'commercial_insured_company'],
            'commercial_org_code'          => [VehicleInsurance::class, 'commercial_org_code'],
            'commercial_insurance_company' => [VehicleInsurance::class, 'commercial_insurance_company'],
            'is_company_borne'             => [VehicleInsurance::class, 'is_company_borne'],
            'vi_remark'                    => [VehicleInsurance::class, 'vi_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']                      = Vehicle::plateNoKv($item['plate_no'] ?? null);
            $item['compulsory_plate_no']        = $item['plate_no'] ?? null;
            $item['carrier_liability_plate_no'] = $item['plate_no'] ?? null;
            $item['commercial_plate_no']        = $item['plate_no'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_id' => ['required', 'integer'],
            // 交强险字段
            'compulsory_plate_no'          => ['nullable', 'string', 'max:50'],
            'compulsory_policy_number'     => ['nullable', 'string', 'max:50'],
            'compulsory_start_date'        => ['nullable', 'date'],
            'compulsory_end_date'          => ['nullable', 'date', 'after:compulsory_start_date'],
            'compulsory_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
            'compulsory_insured_company'   => ['nullable', 'string', 'max:255'],
            'compulsory_org_code'          => ['nullable', 'string', 'max:50'],
            'compulsory_insurance_company' => ['nullable', 'string', 'max:255'],
            // 承运人责任险字段
            'carrier_liability_plate_no'          => ['nullable', 'string', 'max:50'],
            'carrier_liability_policy_number'     => ['nullable', 'string', 'max:50'],
            'carrier_liability_start_date'        => ['nullable', 'date'],
            'carrier_liability_end_date'          => ['nullable', 'date', 'after:carrier_liability_start_date'],
            'carrier_liability_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
            'carrier_liability_insured_company'   => ['nullable', 'string', 'max:255'],
            'carrier_liability_org_code'          => ['nullable', 'string', 'max:50'],
            'carrier_liability_insurance_company' => ['nullable', 'string', 'max:255'],
            // 商业险字段
            'commercial_plate_no'          => ['nullable', 'string', 'max:50'],
            'commercial_policy_number'     => ['nullable', 'string', 'max:50'],
            'commercial_start_date'        => ['nullable', 'date'],
            'commercial_end_date'          => ['nullable', 'date', 'after:commercial_start_date'],
            'commercial_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
            'commercial_insured_company'   => ['nullable', 'string', 'max:255'],
            'commercial_org_code'          => ['nullable', 'string', 'max:50'],
            'commercial_insurance_company' => ['nullable', 'string', 'max:255'],

            // 其他字段
            'is_company_borne' => ['nullable', 'boolean'],
            'vi_remark'        => ['nullable', 'string'],
        ];

        $validator = Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {};
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $vehicleInsurance = VehicleInsurance::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function viCompulsoryPolicyFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function viCompulsoryPolicyPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function viCompulsoryPolicyAddendumFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function viCarrierLiabilityPolicyFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function viCarrierLiabilityPolicyPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function viCommercialPolicyFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function viCommercialPolicyPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function viCommercialPolicyAddendumFile(): Attribute
    {
        return $this->uploadFile();
    }
}
