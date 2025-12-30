<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\VehicleRepair\VrCustodyVehicle;
use App\Enum\VehicleRepair\VrPickupStatus;
use App\Enum\VehicleRepair\VrRepairAttribute;
use App\Enum\VehicleRepair\VrRepairStatus;
use App\Enum\VehicleRepair\VrSettlementMethod;
use App\Enum\VehicleRepair\VrSettlementStatus;
use App\Models\_\ImportTrait;
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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('维修', '记录')]
#[ColumnDesc('vr_id')]
#[ColumnDesc('vr_ve_id')]
#[ColumnDesc('vr_plate_no', required: true)]
#[ColumnDesc('vr_sc_id')]
#[ColumnDesc('vr_entry_datetime', required: true, type: ColumnType::DATETIME)]
#[ColumnDesc('vr_mileage')]
#[ColumnDesc('vr_repair_cost')]
#[ColumnDesc('vr_delay_days')]
#[ColumnDesc('vr_repair_content')]
#[ColumnDesc('vr_departure_datetime', type: ColumnType::DATETIME)]
#[ColumnDesc('vr_repair_status', enum_class: VrRepairStatus::class)]
#[ColumnDesc('vr_settlement_status', enum_class: VrSettlementStatus::class)]
#[ColumnDesc('vr_pickup_status', enum_class: VrPickupStatus::class)]
#[ColumnDesc('vr_settlement_method', enum_class: VrSettlementMethod::class)]
#[ColumnDesc('vr_custody_vehicle', enum_class: VrCustodyVehicle::class)]
#[ColumnDesc('vr_repair_attribute', enum_class: VrRepairAttribute::class)]
#[ColumnDesc('vr_remark')]
#[ColumnDesc('vr_add_should_pay')]
#[ColumnDesc('vr_additional_photos')]
#[ColumnDesc('vr_repair_info')]
/**
 * @property int               $vr_id                      维修记录序号
 * @property int               $vr_ve_id                   车辆序号；指向车辆表
 * @property null|int          $vr_sc_id                   租车合同序号
 * @property int               $vr_vc_id                   修理厂序号
 * @property Carbon            $vr_entry_datetime          进厂日时
 * @property null|int          $vr_mileage                 维修公里数
 * @property null|float        $vr_repair_cost             维修金额（元）
 * @property null|int          $vr_delay_days              延期天数（天）
 * @property string            $vr_repair_content          维修内容
 * @property null|Carbon       $vr_departure_datetime      出厂日时
 * @property mixed             $vr_repair_status           维修状态；修理中: in_progress、已修好: completed、待进场: pending_entry
 * @property mixed             $vr_settlement_status       结算状态；未结算: unsettled、已结算: settled、已确认: confirmed
 * @property mixed             $vr_pickup_status           提车状态；未提车: not_picked_up、已提车: picked_up
 * @property mixed             $vr_settlement_method       结算方式；承包内: internal、承包外: external、司机自费: driver
 * @property mixed             $vr_custody_vehicle         代管车辆；全托管: full、半托管: partial
 * @property mixed             $vr_repair_attribute        维修属性；日常维修: routine、出险维修: insurance、整改维修: rectification、质保维修: warranty、整备维修: preparation、事故维修: accident
 * @property null|string       $vr_remark                  维修备注
 * @property null|int          $vr_add_should_pay          自动添加为客户应收款；0：否，1：是
 * @property null|mixed        $vr_additional_photos       附加照片；存储照片的路径或链接
 * @property null|mixed        $vr_repair_info             车辆维修信息；存储详细的维修信息
 * @property Vehicle           $Vehicle
 * @property VehicleInspection $VehicleInspection
 * @property mixed             $vr_repair_status_label     维修状态-中文
 * @property mixed             $vr_settlement_status_label 结算状态-中文
 * @property mixed             $vr_pickup_status_label     提车状态-中文
 * @property mixed             $vr_settlement_method_label 结算方式-中文
 * @property mixed             $vr_custody_vehicle_label   代管车辆-中文
 * @property mixed             $vr_repair_attribute_label  维修属性-中文
 * @property Payment           $Payment
 * @property Payment           $PaymentAll
 * @property VehicleCenter     $VehicleCenter
 */
class VehicleRepair extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'vr_created_at';
    public const UPDATED_AT = 'vr_updated_at';
    public const UPDATED_BY = 'vr_updated_by';

    protected $primaryKey = 'vr_id';

    protected $guarded = ['vr_id'];

    protected $attributes = [];

    protected $casts = [
        'vr_entry_datetime'     => 'datetime:Y-m-d H:i',
        'vr_departure_datetime' => 'datetime:Y-m-d H:i',
        'vr_repair_cost'        => 'decimal:2',
        'vr_add_should_pay'     => 'boolean',
        'vr_repair_status'      => VrRepairStatus::class,
        'vr_settlement_status'  => VrSettlementStatus::class,
        'vr_pickup_status'      => VrPickupStatus::class,
        'vr_settlement_method'  => VrSettlementMethod::class,
        'vr_custody_vehicle'    => VrCustodyVehicle::class,
        'vr_repair_attribute'   => VrRepairAttribute::class,
    ];

    protected $appends = [
        'vr_repair_status_label',
        'vr_settlement_status_label',
        'vr_pickup_status_label',
        'vr_settlement_method_label',
        'vr_custody_vehicle_label',
        'vr_repair_attribute_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vr_ve_id', 've_id');
    }

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'vr_sc_id', 'sc_id');
    }

    public function VehicleCenter(): BelongsTo
    {
        return $this->belongsTo(VehicleCenter::class, 'vr_vc_id', 'vc_id');
    }

    public function Payment(): HasOne
    {
        $p_pt_id = PPtId::REPAIR_FEE;

        return $this->hasOne(Payment::class, 'p_vr_id', 'vr_id')
            ->where('p_pt_id', '=', $p_pt_id)->where('p_is_valid', '=', PIsValid::VALID)
            ->withDefault(
                [
                    'p_pt_id'           => $p_pt_id,
                    'payment_type'      => PaymentType::query()->where('pt_id', '=', $p_pt_id)->first(),
                    'p_should_pay_date' => now()->format('Y-m-d'),
                    'p_pay_status'      => PPayStatus::UNPAID,
                ]
            )
        ;
    }

    public function PaymentAll(): HasOne
    {
        $pt_id = PPtId::REPAIR_FEE;

        return $this->hasOne(Payment::class, 'p_vr_id', 'vr_id')
            ->where('p_pt_id', '=', $pt_id)
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Vehicle.plate_no'                    => fn ($item) => $item->plate_no,
            'Customer.cu_contact_name'            => fn ($item) => $item->cu_contact_name,
            'VehicleRepair.vr_entry_datetime'     => fn ($item) => $item->vr_entry_datetime_,
            'VehicleRepair.vr_mileage'            => fn ($item) => $item->vr_mileage,
            'VehicleRepair.vr_repair_cost'        => fn ($item) => $item->vr_repair_cost,
            'VehicleRepair.vr_delay_days'         => fn ($item) => $item->vr_delay_days,
            'VehicleCenter.vc_name'               => fn ($item) => $item->vc_name,
            'VehicleRepair.vr_repair_content'     => fn ($item) => $item->vr_repair_content,
            'VehicleRepair.vr_departure_datetime' => fn ($item) => $item->vr_departure_datetime_,
            'VehicleRepair.vr_repair_status'      => fn ($item) => $item->vr_repair_status_label,
            'VehicleRepair.vr_pickup_status'      => fn ($item) => $item->vr_pickup_status_label,
            'VehicleRepair.vr_settlement_status'  => fn ($item) => $item->vr_settlement_status_label,
            'VehicleRepair.vr_custody_vehicle'    => fn ($item) => $item->vr_custody_vehicle_label,
            'VehicleRepair.vr_repair_attribute'   => fn ($item) => $item->vr_repair_attribute_label,
            'VehicleRepair.vr_remark'             => fn ($item) => $item->vr_remark,
            'VehicleRepair.vr_repair_info'        => fn ($item) => str_render($item->vr_repair_info, 'repair_info'),
        ];
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('vehicle_repairs', 'vr')
            ->leftJoin('vehicle_centers as vc', 'vc.vc_id', '=', 'vr.vr_vc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vr.vr_ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.ve_vm_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vr.vr_sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->select('vr.*', 'vc.vc_name', 've.ve_plate_no', 'cu.cu_contact_name', 'cu.cu_contact_phone', '_vm.vm_brand_name', '_vm.vm_model_name')
            ->addSelect(
                DB::raw(VrRepairAttribute::toCaseSQL()),
                DB::raw(VrCustodyVehicle::toCaseSQL()),
                DB::raw(VrPickupStatus::toCaseSQL()),
                DB::raw(VrSettlementMethod::toCaseSQL()),
                DB::raw(VrSettlementStatus::toCaseSQL()),
                DB::raw(VrRepairStatus::toCaseSQL()),
                DB::raw(VrRepairStatus::toColorSQL()),
                DB::raw('EXTRACT(EPOCH FROM vr_departure_datetime - vr_entry_datetime) / 86400.0 AS vr_interval_day'),
                DB::raw("to_char(vr_entry_datetime, 'YYYY-MM-DD HH24:MI') as vr_entry_datetime_"),
                DB::raw("to_char(vr_departure_datetime, 'YYYY-MM-DD HH24:MI') as vr_departure_datetime_"),
            )
        ;
    }

    public static function indexStatValue($list): bool
    {
        $status = false;
        foreach ($list as $item) {
            /** @var VehicleRepair $item */
            if (VrRepairStatus::COMPLETED !== $item->vr_repair_status) {
                $status = true;
            }
        }

        return $status;
    }

    public static function importColumns(): array
    {
        return [
            'vr_plate_no'           => [Vehicle::class, 've_plate_no'],
            'vr_entry_datetime'     => [VehicleRepair::class, 'vr_entry_datetime'],
            'vr_mileage'            => [VehicleRepair::class, 'vr_mileage'],
            'vr_repair_cost'        => [VehicleRepair::class, 'vr_repair_cost'],
            'vr_delay_days'         => [VehicleRepair::class, 'vr_delay_days'],
            'vr_vc_name'            => [VehicleCenter::class, 'vc_name'],
            'vr_repair_content'     => [VehicleRepair::class, 'vr_repair_content'],
            'vr_departure_datetime' => [VehicleRepair::class, 'vr_departure_datetime'],
            'vr_repair_status'      => [VehicleRepair::class, 'vr_repair_status'],
            'vr_settlement_status'  => [VehicleRepair::class, 'vr_settlement_status'],
            'vr_pickup_status'      => [VehicleRepair::class, 'vr_pickup_status'],
            'vr_settlement_method'  => [VehicleRepair::class, 'vr_settlement_method'],
            'vr_custody_vehicle'    => [VehicleRepair::class, 'vr_custody_vehicle'],
            'vr_repair_attribute'   => [VehicleRepair::class, 'vr_repair_attribute'],
            'vr_remark'             => [VehicleRepair::class, 'vr_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['vr_ve_id']             = Vehicle::plateNoKv($item['vr_plate_no'] ?? null);
            $item['vr_vc_id']             = VehicleCenter::nameKv($item['vr_vc_name'] ?? null);
            $item['vr_repair_status']     = VrRepairStatus::searchValue($item['vr_repair_status'] ?? null);
            $item['vr_settlement_status'] = VrSettlementStatus::searchValue($item['vr_settlement_status'] ?? null);
            $item['vr_pickup_status']     = VrPickupStatus::searchValue($item['vr_pickup_status'] ?? null);
            $item['vr_settlement_method'] = VrSettlementMethod::searchValue($item['vr_settlement_method'] ?? null);
            $item['vr_custody_vehicle']   = VrCustodyVehicle::searchValue($item['vr_custody_vehicle'] ?? null);
            $item['vr_repair_attribute']  = VrRepairAttribute::searchValue($item['vr_repair_attribute'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'vr_ve_id'              => ['required', 'integer'],
            'vr_entry_datetime'     => ['required', 'date'],
            'vr_mileage'            => ['nullable', 'integer', 'min:0'],
            'vr_repair_cost'        => ['nullable', 'decimal:0,2', 'gte:0'],
            'vr_delay_days'         => ['nullable', 'integer', 'min:0'],
            'vr_vc_id'              => ['required', 'integer'],
            'vr_repair_content'     => ['required', 'string'],
            'vr_departure_datetime' => ['nullable', 'date'],
            'vr_repair_status'      => ['required', 'string', Rule::in(VrRepairStatus::label_keys())],
            'vr_settlement_status'  => ['required', 'string', Rule::in(VrSettlementStatus::label_keys())],
            'vr_pickup_status'      => ['required', 'string', Rule::in(VrPickupStatus::label_keys())],
            'vr_settlement_method'  => ['required', 'string', Rule::in(VrSettlementMethod::label_keys())],
            'vr_custody_vehicle'    => ['required', 'string', Rule::in(VrCustodyVehicle::label_keys())],
            'vr_repair_attribute'   => ['required', 'string', Rule::in(VrRepairAttribute::label_keys())],
            'vr_remark'             => ['nullable', 'string'],
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
            $vehicleRepair = VehicleRepair::query()->create($input);
        };
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function vrRepairStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vr_repair_status')?->label
        );
    }

    protected function vrSettlementStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vr_settlement_status')?->label
        );
    }

    protected function vrPickupStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vr_pickup_status')?->label
        );
    }

    protected function vrSettlementMethodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vr_settlement_method')?->label
        );
    }

    protected function vrCustodyVehicleLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vr_custody_vehicle')?->label
        );
    }

    protected function vrRepairAttributeLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('vr_repair_attribute')?->label
        );
    }

    protected function vrAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function vrRepairInfo(): Attribute
    {
        return $this->arrayInfo();
    }
}
