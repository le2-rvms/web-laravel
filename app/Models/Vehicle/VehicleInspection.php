<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\VehicleInspection\ViDrivingLicense;
use App\Enum\VehicleInspection\ViInspectionType;
use App\Enum\VehicleInspection\ViOperationLicense;
use App\Enum\VehicleInspection\ViPolicyCopy;
use App\Enum\VehicleInspection\ViVehicleDamageStatus;
use App\Models\_\ModelTrait;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('验车')]
/**
 * @property int                         $vi_id                          验车序号
 * @property int                         $vi_sc_id                       租车合同序号
 * @property int                         $vi_ve_id                       车辆序号
 * @property string|ViInspectionType     $vi_inspection_type             验车类型；发车或退车
 * @property null|int|ViPolicyCopy       $vi_policy_copy                 保单复印件
 * @property null|int|ViDrivingLicense   $vi_driving_license             行驶证
 * @property null|int|ViOperationLicense $vi_operation_license           营运证（硬卡）
 * @property int|ViVehicleDamageStatus   $vi_vehicle_damage_status       车损状态；TRUE 表示有车损，FALSE 表示无车损
 * @property Carbon                      $vi_inspection_datetime         验车完成日时
 * @property int                         $vi_mileage                     公里数
 * @property mixed                       $vi_processed_by                验车人
 * @property null|float                  $vi_damage_deduction            车损扣款
 * @property null|string                 $vi_remark                      验车备注
 * @property null|bool                   $vi_add_should_pay              是否为客户应收款
 * @property null|mixed                  $vi_additional_photos           附加照片；存储照片路径
 * @property null|mixed                  $vi_inspection_info             验车信息；包括验车照片路径和验车文字描述
 * @property mixed                       $vi_inspection_type_label       验车类型
 * @property mixed                       $vi_policy_copy_label           保单复印件
 * @property mixed                       $vi_driving_license_label       行驶证
 * @property mixed                       $vi_operation_license_label     营运证
 * @property mixed                       $vi_vehicle_damage_status_label 车损状态
 * @property Vehicle                     $Vehicle
 * @property SaleContract                $SaleContract
 * @property Payment                     $Payment
 * @property Payment                     $PaymentAll
 */
class VehicleInspection extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'vi_created_at';
    public const UPDATED_AT = 'vi_updated_at';
    public const UPDATED_BY = 'vi_updated_by';

    protected $primaryKey = 'vi_id';

    protected $guarded = ['vi_id'];

    protected $attributes = [];

    protected $appends = [
        'vi_inspection_type_label',
        'vi_policy_copy_label',
        'vi_driving_license_label',
        'vi_operation_license_label',
        'vi_vehicle_damage_status_label',
    ];

    protected $casts = [
        'vi_inspection_datetime'   => 'datetime:Y-m-d H:i',
        'vi_inspection_type'       => ViInspectionType::class,
        'vi_policy_copy'           => ViPolicyCopy::class,
        'vi_driving_license'       => ViDrivingLicense::class,
        'vi_operation_license'     => ViOperationLicense::class,
        'vi_vehicle_damage_status' => ViVehicleDamageStatus::class,
        'vi_add_should_pay'        => 'boolean',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vi_ve_id', 've_id');
    }

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'vi_sc_id', 'sc_id');
    }

    public function Payment(): HasOne
    {
        $p_pt_id = PPtId::VEHICLE_DAMAGE;

        return $this->hasOne(Payment::class, 'p_vi_id', 'vi_id')
            ->where('p_pt_id', '=', $p_pt_id)->where('p_is_valid', '=', PIsValid::VALID)
            ->withDefault(
                [
                    'p_pt_id'           => $p_pt_id,
                    'p_payment_type'    => PaymentType::query()->where('pt_id', '=', $p_pt_id)->first(),
                    'p_should_pay_date' => now()->format('Y-m-d'),
                    'p_pay_status'      => PPayStatus::UNPAID,
                ]
            )->with('PaymentType')
        ;
    }

    public function PaymentAll(): HasOne
    {
        $p_pt_id = PPtId::VEHICLE_DAMAGE;

        return $this->hasOne(Payment::class, 'p_vi_id', 'vi_id')
            ->where('p_pt_id', '=', $p_pt_id)
            ->with('PaymentType')
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'VehicleInspection.inspection_type'       => fn ($item) => $item->inspection_type_label,
            'Customer.cu_contact_name'                => fn ($item) => $item->cu_contact_name,
            'Vehicle.plate_no'                        => fn ($item) => $item->plate_no,
            'VehicleInspection.policy_copy'           => fn ($item) => $item->policy_copy_label,
            'VehicleInspection.driving_license'       => fn ($item) => $item->driving_license_label,
            'VehicleInspection.operation_license'     => fn ($item) => $item->operation_license_label,
            'VehicleInspection.vi_mileage'            => fn ($item) => $item->vi_mileage,
            'VehicleInspection.vehicle_damage_status' => fn ($item) => $item->vehicle_damage_status_label,
            'VehicleInspection.inspection_datetime'   => fn ($item) => $item->inspection_datetime_,
            'VehicleInspection.vi_remark'             => fn ($item) => $item->vi_remark,
            'VehicleInspection.processed_by'          => fn ($item) => $item->processed_by,
            'VehicleInspection.inspection_info'       => fn ($item) => str_render($item->inspection_info, 'inspection_info'),
        ];
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('vehicle_inspections', 'vi')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vi.vi_ve_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vi.vi_sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->select('vi.*', 've.ve_plate_no', 'cu.cu_contact_name', 'cu.cu_contact_phone')
            ->addSelect(
                DB::raw(ViInspectionType::toCaseSQL()),
                DB::raw(ViPolicyCopy::toCaseSQL()),
                DB::raw(ViDrivingLicense::toCaseSQL()),
                DB::raw(ViOperationLicense::toCaseSQL()),
                DB::raw(ViVehicleDamageStatus::toCaseSQL()),
                DB::raw(ViVehicleDamageStatus::toColorSQL()),
                DB::raw("to_char(vi_inspection_datetime, 'YYYY-MM-DD HH24:MI') as vi_inspection_datetime_"),
            )
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function viAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function viInspectionInfo(): Attribute
    {
        return $this->arrayInfo();
    }

    protected function viInspectionTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vi_inspection_type')?->label
        );
    }

    protected function viPolicyCopyLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vi_policy_copy')?->label
        );
    }

    protected function viDrivingLicenseLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vi_driving_license')?->label
        );
    }

    protected function viOperationLicenseLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vi_operation_license')?->label
        );
    }

    protected function viVehicleDamageStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vi_vehicle_damage_status')?->label
        );
    }
}
