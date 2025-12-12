<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Vehicle\VrCustodyVehicle;
use App\Enum\Vehicle\VrPickupStatus;
use App\Enum\Vehicle\VrRepairAttribute;
use App\Enum\Vehicle\VrRepairStatus;
use App\Enum\Vehicle\VrSettlementMethod;
use App\Enum\Vehicle\VrSettlementStatus;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('维修', '记录')]
#[ColumnDesc('vr_id')]
#[ColumnDesc('ve_id')]
#[ColumnDesc('plate_no', required: true)]
#[ColumnDesc('sc_id')]
#[ColumnDesc('entry_datetime', required: true, type: ColumnType::DATETIME)]
#[ColumnDesc('vr_mileage')]
#[ColumnDesc('repair_cost')]
#[ColumnDesc('delay_days')]
#[ColumnDesc('repair_content')]
#[ColumnDesc('departure_datetime', type: ColumnType::DATETIME)]
#[ColumnDesc('repair_status', enum_class: VrRepairStatus::class)]
#[ColumnDesc('settlement_status', enum_class: VrSettlementStatus::class)]
#[ColumnDesc('pickup_status', enum_class: VrPickupStatus::class)]
#[ColumnDesc('settlement_method', enum_class: VrSettlementMethod::class)]
#[ColumnDesc('custody_vehicle', enum_class: VrCustodyVehicle::class)]
#[ColumnDesc('repair_attribute', enum_class: VrRepairAttribute::class)]
#[ColumnDesc('vr_remark')]
#[ColumnDesc('add_should_pay')]
#[ColumnDesc('additional_photos')]
#[ColumnDesc('repair_info')]
/**
 * @property int               $vr_id                   维修记录序号
 * @property int               $ve_id                   车辆序号；指向车辆表
 * @property null|int          $sc_id                   租车合同序号
 * @property int               $vc_id                   修理厂序号
 * @property Carbon            $entry_datetime          进厂日时
 * @property null|int          $vr_mileage              维修公里数
 * @property null|float        $repair_cost             维修金额（元）
 * @property null|int          $delay_days              延期天数（天）
 * @property string            $repair_content          维修内容
 * @property null|Carbon       $departure_datetime      出厂日时
 * @property mixed             $repair_status           维修状态；修理中: in_progress、已修好: completed、待进场: pending_entry
 * @property mixed             $settlement_status       结算状态；未结算: unsettled、已结算: settled、已确认: confirmed
 * @property mixed             $pickup_status           提车状态；未提车: not_picked_up、已提车: picked_up
 * @property mixed             $settlement_method       结算方式；承包内: internal、承包外: external、司机自费: driver
 * @property mixed             $custody_vehicle         代管车辆；全托管: full、半托管: partial
 * @property mixed             $repair_attribute        维修属性；日常维修: routine、出险维修: insurance、整改维修: rectification、质保维修: warranty、整备维修: preparation、事故维修: accident
 * @property null|string       $vr_remark               维修备注
 * @property null|int          $add_should_pay          自动添加为客户应收款；0：否，1：是
 * @property null|mixed        $additional_photos       附加照片；存储照片的路径或链接
 * @property null|mixed        $repair_info             车辆维修信息；存储详细的维修信息
 * @property Vehicle           $Vehicle
 * @property VehicleInspection $VehicleInspection
 * @property mixed             $repair_status_label     维修状态-中文
 * @property mixed             $settlement_status_label 结算状态-中文
 * @property mixed             $pickup_status_label     提车状态-中文
 * @property mixed             $settlement_method_label 结算方式-中文
 * @property mixed             $custody_vehicle_label   代管车辆-中文
 * @property mixed             $repair_attribute_label  维修属性-中文
 * @property Payment           $Payment
 * @property Payment           $PaymentAll
 * @property VehicleCenter     $VehicleCenter
 */
class VehicleRepair extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'vr_id';

    protected $guarded = ['vr_id'];

    protected $attributes = [];

    protected $casts = [
        'entry_datetime'     => 'datetime:Y-m-d H:i',
        'departure_datetime' => 'datetime:Y-m-d H:i',
        'repair_cost'        => 'decimal:2',
        'add_should_pay'     => 'boolean',
        'repair_status'      => VrRepairStatus::class,
        'settlement_status'  => VrSettlementStatus::class,
        'pickup_status'      => VrPickupStatus::class,
        'settlement_method'  => VrSettlementMethod::class,
        'custody_vehicle'    => VrCustodyVehicle::class,
        'repair_attribute'   => VrRepairAttribute::class,
    ];

    protected $appends = [
        'repair_status_label',
        'settlement_status_label',
        'pickup_status_label',
        'settlement_method_label',
        'custody_vehicle_label',
        'repair_attribute_label',
        //        'vi_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id');
    }

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'sc_id', 'sc_id');
    }

    public function VehicleCenter(): BelongsTo
    {
        return $this->belongsTo(VehicleCenter::class, 'vc_id', 'vc_id');
    }

    public function Payment(): HasOne
    {
        $pt_id = RpPtId::REPAIR_FEE;

        return $this->hasOne(Payment::class, 'vr_id', 'vr_id')
            ->where('pt_id', '=', $pt_id)->where('is_valid', '=', RpIsValid::VALID)
            ->withDefault(
                [
                    'pt_id'           => $pt_id,
                    'payment_type'    => PaymentType::query()->where('pt_id', '=', $pt_id)->first(),
                    'should_pay_date' => now()->format('Y-m-d'),
                    'pay_status'      => RpPayStatus::UNPAID,
                ]
            )
        ;
    }

    public function PaymentAll(): HasOne
    {
        $pt_id = RpPtId::REPAIR_FEE;

        return $this->hasOne(Payment::class, 'vr_id', 'vr_id')
            ->where('pt_id', '=', $pt_id)
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Vehicle.plate_no'                 => fn ($item) => $item->plate_no,
            'Customer.contact_name'            => fn ($item) => $item->contact_name,
            'VehicleRepair.entry_datetime'     => fn ($item) => $item->entry_datetime_,
            'VehicleRepair.vr_mileage'         => fn ($item) => $item->vr_mileage,
            'VehicleRepair.repair_cost'        => fn ($item) => $item->repair_cost,
            'VehicleRepair.delay_days'         => fn ($item) => $item->delay_days,
            'VehicleCenter.vc_name'            => fn ($item) => $item->vc_name,
            'VehicleRepair.repair_content'     => fn ($item) => $item->repair_content,
            'VehicleRepair.departure_datetime' => fn ($item) => $item->departure_datetime_,
            'VehicleRepair.repair_status'      => fn ($item) => $item->repair_status_label,
            'VehicleRepair.pickup_status'      => fn ($item) => $item->pickup_status_label,
            'VehicleRepair.settlement_status'  => fn ($item) => $item->settlement_status_label,
            'VehicleRepair.custody_vehicle'    => fn ($item) => $item->custody_vehicle_label,
            'VehicleRepair.repair_attribute'   => fn ($item) => $item->repair_attribute_label,
            'VehicleRepair.vr_remark'          => fn ($item) => $item->vr_remark,
            'VehicleRepair.repair_info'        => fn ($item) => str_render($item->repair_info, 'repair_info'),
        ];
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $sc_id = $search['sc_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;
        $vc_id = $search['vc_id'] ?? null;

        return DB::query()
            ->from('vehicle_repairs', 'vr')
            ->leftJoin('vehicle_centers as vc', 'vc.vc_id', '=', 'vr.vc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vr.ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.vm_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vr.sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vr.ve_id', '=', $ve_id);
            })
            ->when($sc_id, function (Builder $query) use ($sc_id) {
                $query->where('vr.sc_id', '=', $sc_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('sc.cu_id', '=', $cu_id);
            })
            ->when($vc_id, function (Builder $query) use ($vc_id) {
                $query->where('vr.vc_id', '=', $vc_id);
            })
            ->when(
                null === $ve_id && null === $sc_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vr.vr_id');
                },
                function (Builder $query) {
                    $query->orderBy('vr.vr_id');
                }
            )
            ->select('vr.*', 'vc.vc_name', 've.plate_no', 'cu.contact_name', 'cu.contact_phone', '_vm.brand_name', '_vm.model_name')
            ->addSelect(
                DB::raw(VrRepairAttribute::toCaseSQL()),
                DB::raw(VrCustodyVehicle::toCaseSQL()),
                DB::raw(VrPickupStatus::toCaseSQL()),
                DB::raw(VrSettlementMethod::toCaseSQL()),
                DB::raw(VrSettlementStatus::toCaseSQL()),
                DB::raw(VrRepairStatus::toCaseSQL()),
                DB::raw(VrRepairStatus::toColorSQL()),
                DB::raw('EXTRACT(EPOCH FROM departure_datetime - entry_datetime) / 86400.0 AS vr_interval_day'),
                DB::raw("to_char(entry_datetime, 'YYYY-MM-DD HH24:MI') as entry_datetime_"),
                DB::raw("to_char(departure_datetime, 'YYYY-MM-DD HH24:MI') as departure_datetime_"),
            )
        ;
    }

    public static function indexStat($list): bool
    {
        $status = false;
        foreach ($list as $item) {
            /** @var VehicleRepair $item */
            if (VrRepairStatus::COMPLETED !== $item->repair_status) {
                $status = true;
            }
        }

        return $status;
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'           => [VehicleRepair::class, 'plate_no'],
            'entry_datetime'     => [VehicleRepair::class, 'entry_datetime'],
            'vr_mileage'         => [VehicleRepair::class, 'vr_mileage'],
            'repair_cost'        => [VehicleRepair::class, 'repair_cost'],
            'delay_days'         => [VehicleRepair::class, 'delay_days'],
            'vc_name'            => [VehicleCenter::class, 'vc_name'],
            'repair_content'     => [VehicleRepair::class, 'repair_content'],
            'departure_datetime' => [VehicleRepair::class, 'departure_datetime'],
            'repair_status'      => [VehicleRepair::class, 'repair_status'],
            'settlement_status'  => [VehicleRepair::class, 'settlement_status'],
            'pickup_status'      => [VehicleRepair::class, 'pickup_status'],
            'settlement_method'  => [VehicleRepair::class, 'settlement_method'],
            'custody_vehicle'    => [VehicleRepair::class, 'custody_vehicle'],
            'repair_attribute'   => [VehicleRepair::class, 'repair_attribute'],
            'vr_remark'          => [VehicleRepair::class, 'vr_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']             = Vehicle::plateNoKv($item['plate_no'] ?? null);
            $item['vc_id']             = VehicleCenter::nameKv($item['vc_name'] ?? null);
            $item['repair_status']     = VrRepairStatus::searchValue($item['repair_status'] ?? null);
            $item['settlement_status'] = VrSettlementStatus::searchValue($item['settlement_status'] ?? null);
            $item['pickup_status']     = VrPickupStatus::searchValue($item['pickup_status'] ?? null);
            $item['settlement_method'] = VrSettlementMethod::searchValue($item['settlement_method'] ?? null);
            $item['custody_vehicle']   = VrCustodyVehicle::searchValue($item['custody_vehicle'] ?? null);
            $item['repair_attribute']  = VrRepairAttribute::searchValue($item['repair_attribute'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_id'              => ['required', 'integer'],
            'entry_datetime'     => ['required', 'date'],
            'vr_mileage'         => ['nullable', 'integer', 'min:0'],
            'repair_cost'        => ['nullable', 'decimal:0,2', 'gte:0'],
            'delay_days'         => ['nullable', 'integer', 'min:0'],
            'vc_id'              => ['required', 'integer'],
            'repair_content'     => ['required', 'string'],
            'departure_datetime' => ['nullable', 'date'],
            'repair_status'      => ['required', 'string', Rule::in(VrRepairStatus::label_keys())],
            'settlement_status'  => ['required', 'string', Rule::in(VrSettlementStatus::label_keys())],
            'pickup_status'      => ['required', 'string', Rule::in(VrPickupStatus::label_keys())],
            'settlement_method'  => ['required', 'string', Rule::in(VrSettlementMethod::label_keys())],
            'custody_vehicle'    => ['required', 'string', Rule::in(VrCustodyVehicle::label_keys())],
            'repair_attribute'   => ['required', 'string', Rule::in(VrRepairAttribute::label_keys())],
            'vr_remark'          => ['nullable', 'string'],
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

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function repairStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('repair_status')?->label
        );
    }

    protected function settlementStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('settlement_status')?->label
        );
    }

    protected function pickupStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('pickup_status')?->label
        );
    }

    protected function settlementMethodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('settlement_method')?->label
        );
    }

    protected function custodyVehicleLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('custody_vehicle')?->label
        );
    }

    protected function repairAttributeLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('repair_attribute')?->label
        );
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function repairInfo(): Attribute
    {
        return $this->arrayInfo();
    }
}
