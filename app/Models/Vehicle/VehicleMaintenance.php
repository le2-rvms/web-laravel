<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\VehicleMaintenance\VmCustodyVehicle;
use App\Enum\VehicleMaintenance\VmPickupStatus;
use App\Enum\VehicleMaintenance\VmSettlementMethod;
use App\Enum\VehicleMaintenance\VmSettlementStatus;
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

#[ClassName('车辆保养', '记录')]
#[ColumnDesc('vm_id')]
#[ColumnDesc('vm_ve_id')]
#[ColumnDesc('vm_plate_no', required: true)]
#[ColumnDesc('vm_sc_id')]
#[ColumnDesc('vm_entry_datetime', required: true, type: ColumnType::DATETIME)]
#[ColumnDesc('vm_maintenance_amount', required: true)]
#[ColumnDesc('vm_entry_mileage')]
#[ColumnDesc('vm_next_maintenance_date', type: ColumnType::DATE)]
#[ColumnDesc('vm_departure_datetime', type: ColumnType::DATETIME)]
#[ColumnDesc('vm_maintenance_mileage')]
#[ColumnDesc('vm_settlement_status', enum_class: VmSettlementStatus::class)]
#[ColumnDesc('vm_pickup_status', enum_class: VmPickupStatus::class)]
#[ColumnDesc('vm_settlement_method', enum_class: VmSettlementMethod::class)]
#[ColumnDesc('vm_custody_vehicle', enum_class: VmCustodyVehicle::class)]
#[ColumnDesc('vm_remark')]
#[ColumnDesc('vm_additional_photos')]
#[ColumnDesc('vm_maintenance_info')]
/**
 * @property int           $vm_id                      保养序号
 * @property int           $vm_ve_id                   车辆序号
 * @property null|int      $vm_sc_id                   租车合同序号
 * @property int           $vm_vc_id                   修理厂序号
 * @property Carbon        $vm_entry_datetime          进厂日时
 * @property float         $vm_maintenance_amount      保养金额;元
 * @property int           $vm_entry_mileage           进厂公里数
 * @property null|Carbon   $vm_next_maintenance_date   下次保养日期
 * @property Carbon        $vm_departure_datetime      出厂日时
 * @property null|int      $vm_maintenance_mileage     保养里程;公里
 * @property null|string   $vm_settlement_status       结算状态
 * @property null|string   $vm_pickup_status           提车状态
 * @property null|string   $vm_settlement_method       结算方式
 * @property null|string   $vm_custody_vehicle         代管车辆
 * @property null|string   $vm_vm_remark               保养备注
 * @property null|mixed    $vm_additional_photos       附加照片;JSON 格式存储图片路径或链接
 * @property null|mixed    $vm_maintenance_info        车辆保养信息
 * @property null|string   $vm_settlement_status_label 结算状态
 * @property null|string   $vm_pickup_status_label     提车状态
 * @property null|string   $vm_settlement_method_label 结算方式
 * @property null|string   $vm_custody_vehicle_label   代管车辆
 * @property Vehicle       $Vehicle
 * @property SaleContract  $SaleContract
 * @property Payment       $Payment
 * @property Payment       $PaymentAll
 * @property null|int      $add_should_pay
 * @property VehicleCenter $VehicleCenter
 */
class VehicleMaintenance extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'vm_created_at';
    public const UPDATED_AT = 'vm_updated_at';
    public const UPDATED_BY = 'vm_updated_by';

    protected $primaryKey = 'vm_id';

    protected $guarded = ['vm_id'];

    protected $attributes = [];

    protected $casts = [
        'vm_entry_datetime'        => 'datetime:Y-m-d H:i',
        'vm_departure_datetime'    => 'datetime:Y-m-d H:i',
        'vm_maintenance_amount'    => 'decimal:2',
        'vm_settlement_status'     => VmSettlementStatus::class,
        'vm_pickup_status'         => VmPickupStatus::class,
        'vm_settlement_method'     => VmSettlementMethod::class,
        'vm_custody_vehicle'       => VmCustodyVehicle::class,
        'vm_next_maintenance_date' => 'datetime:Y-m-d',
    ];

    protected $appends = [
        'vm_settlement_status_label',
        'vm_pickup_status_label',
        'vm_settlement_method_label',
        'vm_custody_vehicle_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vm_ve_id', 've_id');
    }

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'vm_sc_id', 'sc_id');
    }

    public function Payment(): HasOne
    {
        $p_pt_id = PPtId::MAINTENANCE_FEE;

        return $this->hasOne(Payment::class, 'p_vm_id', 'vm_id')
            ->where('p_pt_id', '=', $p_pt_id)->where('p_is_valid', '=', PIsValid::VALID)
            ->withDefault(
                [
                    'p_pt_id'           => $p_pt_id,
                    'p_payment_type'    => PaymentType::query()->where('pt_id', '=', $p_pt_id)->first(),
                    'p_should_pay_date' => now()->format('Y-m-d'),
                    'p_pay_status'      => PPayStatus::UNPAID,
                ]
            )
        ;
    }

    public function PaymentAll(): HasOne
    {
        $p_pt_id = PPtId::MAINTENANCE_FEE;

        return $this->hasOne(Payment::class, 'p_vm_id', 'vm_id')
            ->where('p_pt_id', '=', $p_pt_id)
        ;
    }

    public function VehicleCenter(): BelongsTo
    {
        return $this->belongsTo(VehicleCenter::class, 'vm_vc_id', 'vc_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $vm_ve_id = $search['vm_ve_id'] ?? null;
        $vm_cu_id = $search['vm_cu_id'] ?? null;
        $vm_vc_id = $search['vm_vc_id'] ?? null;

        return DB::query()
            ->from('vehicle_maintenances', 'vm')
            ->leftJoin('vehicle_centers as vc', 'vc.vc_id', '=', 'vm.vm_vc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vm.vm_ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.ve_vm_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vm.vm_sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->when($vm_ve_id, function (Builder $query) use ($vm_ve_id) {
                $query->where('vm.ve_id', '=', $vm_ve_id);
            })
            ->when($vm_cu_id, function (Builder $query) use ($vm_cu_id) {
                $query->where('sc.sc_cu_id', '=', $vm_cu_id);
            })
            ->when($vm_vc_id, function (Builder $query) use ($vm_vc_id) {
                $query->where('vm.vc_id', '=', $vm_vc_id);
            })
            ->when(
                null === $vm_ve_id && null === $vm_cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vm.vm_id');
                },
                function (Builder $query) {
                    $query->orderBy('vm.vm_id');
                }
            )
            ->select('vm.*', 'vc.vc_name', 've.ve_plate_no', 'cu.cu_contact_name', 'cu.cu_contact_phone', '_vm.vm_brand_name', '_vm.vm_model_name')
            ->addSelect(
                DB::raw(VmCustodyVehicle::toCaseSQL()),
                DB::raw(VmPickupStatus::toCaseSQL()),
                DB::raw(VmSettlementMethod::toCaseSQL()),
                DB::raw(VmSettlementStatus::toCaseSQL()),
                DB::raw("to_char(vm_entry_datetime, 'YYYY-MM-DD HH24:MI') as vm_entry_datetime_"),
                DB::raw("to_char(vm_departure_datetime, 'YYYY-MM-DD HH24:MI') as vm_departure_datetime_"),
            )
            ->addSelect(DB::raw('EXTRACT(EPOCH FROM vm_departure_datetime - vm_entry_datetime) / 86400.0 as vm_interval_day'))
        ;
    }

    public static function indexColumns()
    {
        return [
            'Vehicle.ve_plate_no'                       => fn ($item) => $item->ve_plate_no,
            'Customer.cu_contact_name'                  => fn ($item) => $item->cu_contact_name,
            'VehicleCenter.vc_name'                     => fn ($item) => $item->vc_name,
            'VehicleMaintenance.vm_entry_datetime'      => fn ($item) => $item->vm_entry_datetime_,
            'VehicleMaintenance.vm_maintenance_amount'  => fn ($item) => $item->vm_maintenance_amount,
            'VehicleMaintenance.vm_entry_mileage'       => fn ($item) => $item->vm_entry_mileage,
            'VehicleMaintenance.vm_departure_datetime'  => fn ($item) => $item->vm_departure_datetime_,
            'VehicleMaintenance.vm_maintenance_mileage' => fn ($item) => $item->vm_maintenance_mileage,
            'VehicleMaintenance.vm_settlement_status'   => fn ($item) => $item->vm_settlement_status_label,
            'VehicleMaintenance.vm_pickup_status'       => fn ($item) => $item->vm_pickup_status_label,
            'VehicleMaintenance.vm_settlement_method'   => fn ($item) => $item->vm_settlement_method_label,
            'VehicleMaintenance.vm_custody_vehicle'     => fn ($item) => $item->vm_custody_vehicle_label,
            'VehicleMaintenance.vm_remark'              => fn ($item) => $item->vm_remark,
            'VehicleMaintenance.vm_maintenance_info'    => fn ($item) => str_render($item->vm_maintenance_info, 'vm_maintenance_info'),
        ];
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'              => [VehicleMaintenance::class, 'plate_no'],
            'vc_name'               => [VehicleCenter::class, 'vc_name'],
            'entry_datetime'        => [VehicleMaintenance::class, 'entry_datetime'],
            'maintenance_amount'    => [VehicleMaintenance::class, 'maintenance_amount'],
            'entry_mileage'         => [VehicleMaintenance::class, 'entry_mileage'],
            'next_maintenance_date' => [VehicleMaintenance::class, 'next_maintenance_date'],
            'departure_datetime'    => [VehicleMaintenance::class, 'departure_datetime'],
            'maintenance_mileage'   => [VehicleMaintenance::class, 'maintenance_mileage'],
            'settlement_status'     => [VehicleMaintenance::class, 'settlement_status'],
            'pickup_status'         => [VehicleMaintenance::class, 'pickup_status'],
            'settlement_method'     => [VehicleMaintenance::class, 'settlement_method'],
            'custody_vehicle'       => [VehicleMaintenance::class, 'custody_vehicle'],
            'vm_remark'             => [VehicleMaintenance::class, 'vm_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']             = Vehicle::plateNoKv($item['plate_no'] ?? null);
            $item['vc_id']             = VehicleCenter::nameKv($item['vc_name'] ?? null);
            $item['settlement_status'] = VmSettlementStatus::searchValue($item['settlement_status'] ?? null);
            $item['pickup_status']     = VmPickupStatus::searchValue($item['pickup_status'] ?? null);
            $item['settlement_method'] = VmSettlementMethod::searchValue($item['settlement_method'] ?? null);
            $item['custody_vehicle']   = VmCustodyVehicle::searchValue($item['custody_vehicle'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_id'               => ['required', 'integer'],
            'sc_id'               => ['nullable', 'integer'],
            'entry_datetime'      => ['required', 'date'],
            'entry_mileage'       => ['nullable', 'integer', 'min:0'],
            'maintenance_mileage' => ['nullable', 'integer', 'min:0'],
            'maintenance_amount'  => ['nullable', 'decimal:0,2', 'gte:0'],
            'vc_id'               => ['required', 'integer'],
            'departure_datetime'  => ['nullable', 'date'],
            'settlement_status'   => ['required', 'string', Rule::in(VmSettlementStatus::label_keys())],
            'pickup_status'       => ['required', 'string', Rule::in(VmPickupStatus::label_keys())],
            'settlement_method'   => ['required', 'string', Rule::in(VmSettlementMethod::label_keys())],
            'custody_vehicle'     => ['required', 'string', Rule::in(VmCustodyVehicle::label_keys())],
            'vm_remark'           => ['nullable', 'string'],
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
            $vehicleMaintenance = VehicleMaintenance::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function vmSettlementStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vm_settlement_status')?->label
        );
    }

    protected function vmPickupStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vm_pickup_status')?->label
        );
    }

    protected function vmSettlementMethodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vm_settlement_method')?->label
        );
    }

    protected function vmCustodyVehicleLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vm_custody_vehicle')?->label
        );
    }

    protected function vmAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function vmMaintenanceInfo(): Attribute
    {
        return $this->arrayInfo();
    }
}
