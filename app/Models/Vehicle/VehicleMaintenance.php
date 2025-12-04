<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Vehicle\VmCustodyVehicle;
use App\Enum\Vehicle\VmPickupStatus;
use App\Enum\Vehicle\VmSettlementMethod;
use App\Enum\Vehicle\VmSettlementStatus;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use App\Models\Sale\SaleOrder;
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
#[ColumnDesc('ve_id')]
#[ColumnDesc('plate_no', required: true)]
#[ColumnDesc('so_id')]
#[ColumnDesc('entry_datetime', required: true, type: ColumnType::DATETIME)]
#[ColumnDesc('maintenance_amount', required: true)]
#[ColumnDesc('entry_mileage')]
#[ColumnDesc('next_maintenance_date', type: ColumnType::DATE)]
#[ColumnDesc('departure_datetime', type: ColumnType::DATETIME)]
#[ColumnDesc('maintenance_mileage')]
#[ColumnDesc('settlement_status', enum_class: VmSettlementStatus::class)]
#[ColumnDesc('pickup_status', enum_class: VmPickupStatus::class)]
#[ColumnDesc('settlement_method', enum_class: VmSettlementMethod::class)]
#[ColumnDesc('custody_vehicle', enum_class: VmCustodyVehicle::class)]
#[ColumnDesc('vm_remark')]
#[ColumnDesc('additional_photos')]
#[ColumnDesc('maintenance_info')]
/**
 * @property int           $vm_id                   保养序号
 * @property int           $ve_id                   车辆序号
 * @property null|int      $so_id                   订单序号
 * @property int           $sc_id                   修理厂序号
 * @property Carbon        $entry_datetime          进厂日时
 * @property float         $maintenance_amount      保养金额;元
 * @property int           $entry_mileage           进厂公里数
 * @property null|Carbon   $next_maintenance_date   下次保养日期
 * @property Carbon        $departure_datetime      出厂日时
 * @property null|int      $maintenance_mileage     保养里程;公里
 * @property null|string   $settlement_status       结算状态
 * @property null|string   $pickup_status           提车状态
 * @property null|string   $settlement_method       结算方式
 * @property null|string   $custody_vehicle         代管车辆
 * @property null|string   $vm_remark               保养备注
 * @property null|mixed    $additional_photos       附加照片;JSON 格式存储图片路径或链接
 * @property null|mixed    $maintenance_info        车辆保养信息
 * @property null|string   $settlement_status_label 结算状态
 * @property null|string   $pickup_status_label     提车状态
 * @property null|string   $settlement_method_label 结算方式
 * @property null|string   $custody_vehicle_label   代管车辆
 * @property Vehicle       $Vehicle
 * @property SaleOrder     $SaleOrder
 * @property Payment       $Payment
 * @property Payment       $PaymentAll
 * @property null|int      $add_should_pay
 * @property ServiceCenter $ServiceCenter
 */
class VehicleMaintenance extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'vm_id';

    protected $guarded = ['vm_id'];

    protected $attributes = [];

    protected $casts = [
        'entry_datetime'        => 'datetime:Y-m-d H:i',
        'departure_datetime'    => 'datetime:Y-m-d H:i',
        'maintenance_amount'    => 'decimal:2',
        'settlement_status'     => VmSettlementStatus::class,
        'pickup_status'         => VmPickupStatus::class,
        'settlement_method'     => VmSettlementMethod::class,
        'custody_vehicle'       => VmCustodyVehicle::class,
        'next_maintenance_date' => 'datetime:Y-m-d',
    ];

    protected $appends = [
        'settlement_status_label',
        'pickup_status_label',
        'settlement_method_label',
        'custody_vehicle_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id');
    }

    public function SaleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'so_id', 'so_id');
    }

    public function Payment(): HasOne
    {
        $pt_id = RpPtId::MAINTENANCE_FEE;

        return $this->hasOne(Payment::class, 'vm_id', 'vm_id')
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
        $pt_id = RpPtId::MAINTENANCE_FEE;

        return $this->hasOne(Payment::class, 'vm_id', 'vm_id')
            ->where('pt_id', '=', $pt_id)
        ;
    }

    public function ServiceCenter(): BelongsTo
    {
        return $this->belongsTo(ServiceCenter::class, 'sc_id', 'sc_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;
        $sc_id = $search['sc_id'] ?? null;

        return DB::query()
            ->from('vehicle_maintenances', 'vm')
            ->leftJoin('service_centers as sc', 'sc.sc_id', '=', 'vm.sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vm.ve_id')
            ->leftJoin('vehicle_models as _vm', '_vm.vm_id', '=', 've.vm_id')
            ->leftJoin('sale_orders as so', 'so.so_id', '=', 'vm.so_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vm.ve_id', '=', $ve_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('so.cu_id', '=', $cu_id);
            })
            ->when($sc_id, function (Builder $query) use ($sc_id) {
                $query->where('vm.sc_id', '=', $sc_id);
            })
            ->when(
                null === $ve_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vm.vm_id');
                },
                function (Builder $query) {
                    $query->orderBy('vm.vm_id');
                }
            )
            ->select('vm.*', 'sc.sc_name', 've.plate_no', 'cu.contact_name', 'cu.contact_phone', '_vm.brand_name', '_vm.model_name')
            ->addSelect(
                DB::raw(VmCustodyVehicle::toCaseSQL()),
                DB::raw(VmPickupStatus::toCaseSQL()),
                DB::raw(VmSettlementMethod::toCaseSQL()),
                DB::raw(VmSettlementStatus::toCaseSQL()),
                DB::raw("to_char(entry_datetime, 'YYYY-MM-DD HH24:MI') as entry_datetime_"),
                DB::raw("to_char(departure_datetime, 'YYYY-MM-DD HH24:MI') as departure_datetime_"),
            )
            ->addSelect(DB::raw('EXTRACT(EPOCH FROM departure_datetime - entry_datetime) / 86400.0 as vm_interval_day'))
        ;
    }

    public static function indexColumns()
    {
        return [
            'Vehicle.plate_no'                       => fn ($item) => $item->plate_no,
            'Customer.contact_name'                  => fn ($item) => $item->contact_name,
            'ServiceCenter.sc_name'                  => fn ($item) => $item->sc_name,
            'VehicleMaintenance.entry_datetime'      => fn ($item) => $item->entry_datetime_,
            'VehicleMaintenance.maintenance_amount'  => fn ($item) => $item->maintenance_amount,
            'VehicleMaintenance.entry_mileage'       => fn ($item) => $item->entry_mileage,
            'VehicleMaintenance.departure_datetime'  => fn ($item) => $item->departure_datetime_,
            'VehicleMaintenance.maintenance_mileage' => fn ($item) => $item->maintenance_mileage,
            'VehicleMaintenance.settlement_status'   => fn ($item) => $item->settlement_status_label,
            'VehicleMaintenance.pickup_status'       => fn ($item) => $item->pickup_status_label,
            'VehicleMaintenance.settlement_method'   => fn ($item) => $item->settlement_method_label,
            'VehicleMaintenance.custody_vehicle'     => fn ($item) => $item->custody_vehicle_label,
            'VehicleMaintenance.vm_remark'           => fn ($item) => $item->vm_remark,
            'VehicleMaintenance.maintenance_info'    => fn ($item) => str_render($item->maintenance_info, 'maintenance_info'),
        ];
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'              => [VehicleMaintenance::class, 'plate_no'],
            'sc_name'               => [ServiceCenter::class, 'sc_name'],
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
            $item['sc_id']             = ServiceCenter::nameKv($item['sc_name'] ?? null);
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
            'so_id'               => ['nullable', 'integer'],
            'entry_datetime'      => ['required', 'date'],
            'entry_mileage'       => ['nullable', 'integer', 'min:0'],
            'maintenance_mileage' => ['nullable', 'integer', 'min:0'],
            'maintenance_amount'  => ['nullable', 'decimal:0,2', 'gte:0'],
            'sc_id'               => ['required', 'integer'],
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

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function maintenanceInfo(): Attribute
    {
        return $this->arrayInfo();
    }
}
