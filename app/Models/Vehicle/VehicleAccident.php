<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\VehicleAccident\VaClaimStatus;
use App\Enum\VehicleAccident\VaManagedVehicle;
use App\Enum\VehicleAccident\VaPickupStatus;
use App\Enum\VehicleAccident\VaRepairStatus;
use App\Enum\VehicleAccident\VaSettlementMethod;
use App\Enum\VehicleAccident\VaSettlementStatus;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('出险', '记录')]
#[ColumnDesc('va_id')]
#[ColumnDesc('va_ve_id')]
#[ColumnDesc('va_plate_no', required: true)]
#[ColumnDesc('va_sc_id')]
#[ColumnDesc('va_accident_location')]
#[ColumnDesc('va_accident_dt', type: ColumnType::DATETIME, required: true)]
#[ColumnDesc('va_responsible_party')]
#[ColumnDesc('va_claim_status', enum_class: VaClaimStatus::class)]
#[ColumnDesc('va_self_amount')]
#[ColumnDesc('va_third_party_amount')]
#[ColumnDesc('va_insurance_company')]
#[ColumnDesc('va_description')]
#[ColumnDesc('va_factory_in_dt', type: ColumnType::DATETIME)]
#[ColumnDesc('va_repair_content')]
#[ColumnDesc('va_repair_status', enum_class: VaRepairStatus::class)]
#[ColumnDesc('va_factory_out_dt', type: ColumnType::DATETIME)]
#[ColumnDesc('va_settlement_status', enum_class: VaSettlementStatus::class)]
#[ColumnDesc('va_pickup_status', enum_class: VaPickupStatus::class)]
#[ColumnDesc('va_settlement_method', enum_class: VaSettlementMethod::class)]
#[ColumnDesc('va_managed_vehicle', enum_class: VaManagedVehicle::class)]
#[ColumnDesc('va_remark')]
#[ColumnDesc('va_additional_photos')]
#[ColumnDesc('va_accident_info')]
/**
 * @property int                            $va_id                 出险序号
 * @property int                            $va_ve_id              车辆序号
 * @property null|int                       $va_sc_id              租车合同序号
 * @property int                            $va_vc_id              修理厂序号
 * @property null|string                    $va_accident_location  事故地点
 * @property Carbon                         $va_accident_dt        出险日时
 * @property null|string                    $va_responsible_party  责任方
 * @property null|string|VaClaimStatus      $va_claim_status       理赔状态
 * @property null|float                     $va_self_amount        己方金额(元)
 * @property null|float                     $va_third_party_amount 第三方金额(元)
 * @property null|string                    $va_insurance_company  保险公司
 * @property null|string                    $va_description        详细描述
 * @property null|Carbon                    $va_factory_in_dt      进厂日时
 * @property null|string                    $va_repair_content     维修内容
 * @property null|string|VaRepairStatus     $va_repair_status      维修状态
 * @property null|Carbon                    $va_factory_out_dt     出厂日时
 * @property null|string|VaSettlementStatus $va_settlement_status  结算状态
 * @property null|string|VaPickupStatus     $va_pickup_status      提车状态
 * @property null|string|VaSettlementMethod $va_settlement_method  结算方式
 * @property null|string|VaManagedVehicle   $va_managed_vehicle    代管车辆
 * @property null|string                    $va_remark             出险备注
 * @property null|mixed                     $va_additional_photos  附加照片
 * @property null|array                     $va_accident_info      车辆出险照片以及描述
 * @property Vehicle                        $Vehicle
 * @property Customer                       $Customer
 * @property VehicleCenter                  $VehicleCenter
 */
class VehicleAccident extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'va_created_at';
    public const UPDATED_AT = 'va_updated_at';
    public const UPDATED_BY = 'va_updated_by';

    protected $primaryKey = 'va_id';

    protected $guarded = ['va_id'];

    protected $attributes = [];

    protected $casts = [
        'va_accident_dt'        => 'datetime:Y-m-d H:i',
        'va_factory_in_dt'      => 'datetime:Y-m-d H:i',
        'va_factory_out_dt'     => 'datetime:Y-m-d H:i',
        'va_self_amount'        => 'decimal:2',
        'va_third_party_amount' => 'decimal:2',
        'va_claim_status'       => VaClaimStatus::class,
        'va_repair_status'      => VaRepairStatus::class,
        'va_settlement_status'  => VaSettlementStatus::class,
        'va_pickup_status'      => VaPickupStatus::class,
        'va_settlement_method'  => VaSettlementMethod::class,
        'va_managed_vehicle'    => VaManagedVehicle::class,
    ];

    protected $appends = [
        'va_claim_status_label',
        'va_repair_status_label',
        'va_settlement_status_label',
        'va_pickup_status_label',
        'va_settlement_method_label',
        'va_managed_vehicle_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'va_ve_id', 've_id');
    }

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'va_sc_id', 'sc_id');
    }

    public function VehicleCenter(): BelongsTo
    {
        return $this->belongsTo(VehicleCenter::class, 'va_vc_id', 'vc_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $va_ve_id = $search['va_ve_id'] ?? null;
        $va_sc_id = $search['va_sc_id'] ?? null;
        $va_vc_id = $search['va_vc_id'] ?? null;

        return DB::query()
            ->from('vehicle_accidents', 'va')
            ->leftJoin('vehicle_centers as vc', 'vc.vc_id', '=', 'va.va_vc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'va.va_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'va.va_sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->when($va_ve_id, function (Builder $query) use ($va_ve_id) {
                $query->where('va.va_ve_id', '=', $va_ve_id);
            })
            ->when($va_sc_id, function (Builder $query) use ($va_sc_id) {
                $query->where('va.va_sc_id', '=', $va_sc_id);
            })
            ->when($va_vc_id, function (Builder $query) use ($va_vc_id) {
                $query->where('va.va_vc_id', '=', $va_vc_id);
            })
            ->when(
                null === $va_ve_id && null === $va_sc_id,
                function (Builder $query) {
                    $query->orderByDesc('va.va_id');
                },
                function (Builder $query) {
                    $query->orderBy('va.va_id');
                }
            )
            ->select('va.*', 'vc.vc_name', 've.ve_plate_no', 'cu.cu_contact_name', 'cu.cu_contact_phone', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->addSelect(
                DB::raw(VaClaimStatus::toCaseSQL()),
                DB::raw(VaRepairStatus::toCaseSQL()),
                DB::raw(VaSettlementStatus::toCaseSQL()),
                DB::raw(VaPickupStatus::toCaseSQL()),
                DB::raw(VaSettlementMethod::toCaseSQL()),
                DB::raw(VaManagedVehicle::toCaseSQL()),
                DB::raw('EXTRACT(EPOCH FROM va_factory_in_dt - va_factory_out_dt) / 86400.0 as va_interval_day'),
                DB::raw("to_char(va_accident_dt, 'YYYY-MM-DD HH24:MI') as va_accident_dt_"),
                DB::raw("to_char(va_factory_in_dt, 'YYYY-MM-DD HH24:MI') as va_factory_in_dt_"),
                DB::raw("to_char(va_factory_out_dt, 'YYYY-MM-DD HH24:MI') as va_factory_out_dt_"),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Vehicle.plate_no'                   => fn ($item) => $item->plate_no,
            'Customer.cu_contact_name'           => fn ($item) => $item->cu_contact_name,
            'VehicleAccident.accident_location'  => fn ($item) => $item->accident_location,
            'VehicleAccident.accident_dt'        => fn ($item) => $item->accident_dt_,
            'VehicleAccident.responsible_party'  => fn ($item) => $item->responsible_party,
            'VehicleAccident.claim_status'       => fn ($item) => $item->claim_status_label,
            'VehicleAccident.self_amount'        => fn ($item) => $item->self_amount,
            'VehicleAccident.third_party_amount' => fn ($item) => $item->third_party_amount,
            'VehicleAccident.insurance_company'  => fn ($item) => $item->insurance_company,
            'VehicleAccident.description'        => fn ($item) => $item->description,
            'VehicleAccident.factory_in_dt'      => fn ($item) => $item->factory_in_dt_,
            'VehicleCenter.vc_name'              => fn ($item) => $item->vc_name,
            'VehicleAccident.repair_content'     => fn ($item) => $item->repair_content,
            'VehicleAccident.repair_status'      => fn ($item) => $item->repair_status_label,
            'VehicleAccident.factory_out_dt'     => fn ($item) => $item->factory_out_dt_,
            'VehicleAccident.settlement_status'  => fn ($item) => $item->settlement_status_label,
            'VehicleAccident.pickup_status'      => fn ($item) => $item->pickup_status_label,
            'VehicleAccident.settlement_method'  => fn ($item) => $item->settlement_method_label,
            'VehicleAccident.managed_vehicle'    => fn ($item) => $item->managed_vehicle_label,
            'VehicleAccident.va_remark'          => fn ($item) => $item->va_remark,
            'VehicleAccident.accident_info'      => fn ($item) => str_render($item->accident_info, 'accident_info'),
        ];
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'           => [VehicleAccident::class, 'plate_no'],
            'accident_location'  => [VehicleAccident::class, 'accident_location'],
            'accident_dt'        => [VehicleAccident::class, 'accident_dt'],
            'responsible_party'  => [VehicleAccident::class, 'responsible_party'],
            'claim_status'       => [VehicleAccident::class, 'claim_status'],
            'self_amount'        => [VehicleAccident::class, 'self_amount'],
            'third_party_amount' => [VehicleAccident::class, 'third_party_amount'],
            'insurance_company'  => [VehicleAccident::class, 'insurance_company'],
            'va_description'     => [VehicleAccident::class, 'va_description'],
            'factory_in_dt'      => [VehicleAccident::class, 'factory_in_dt'],
            'vc_name'            => [VehicleCenter::class, 'vc_name'],
            'repair_content'     => [VehicleAccident::class, 'repair_content'],
            'repair_status'      => [VehicleAccident::class, 'repair_status'],
            'factory_out_dt'     => [VehicleAccident::class, 'factory_out_dt'],
            'settlement_status'  => [VehicleAccident::class, 'settlement_status'],
            'pickup_status'      => [VehicleAccident::class, 'pickup_status'],
            'settlement_method'  => [VehicleAccident::class, 'settlement_method'],
            'managed_vehicle'    => [VehicleAccident::class, 'managed_vehicle'],
            'va_remark'          => [VehicleAccident::class, 'va_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']             = Vehicle::plateNoKv($item['plate_no'] ?? null);
            $item['vc_id']             = VehicleCenter::nameKv($item['vc_name'] ?? null);
            $item['claim_status']      = VaClaimStatus::searchValue($item['claim_status'] ?? null);
            $item['repair_status']     = VaRepairStatus::searchValue($item['repair_status'] ?? null);
            $item['settlement_status'] = VaSettlementStatus::searchValue($item['settlement_status'] ?? null);
            $item['pickup_status']     = VaPickupStatus::searchValue($item['pickup_status'] ?? null);
            $item['settlement_method'] = VaSettlementMethod::searchValue($item['settlement_method'] ?? null);
            $item['managed_vehicle']   = VaManagedVehicle::searchValue($item['managed_vehicle'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_id'              => ['required', 'integer'],
            'sc_id'              => ['nullable', 'integer'],
            'accident_location'  => ['nullable', 'string', 'max:255'],
            'accident_dt'        => ['required', 'date'],
            'responsible_party'  => ['nullable', 'string', 'max:255'],
            'claim_status'       => ['nullable', 'string', Rule::in(VaClaimStatus::label_keys())],
            'self_amount'        => ['nullable', 'numeric'],
            'third_party_amount' => ['nullable', 'numeric'],
            'insurance_company'  => ['nullable', 'string', 'max:100'],
            'va_description'     => ['nullable', 'string'],
            'factory_in_dt'      => ['nullable', 'date'],
            'vc_id'              => ['required', 'integer'],
            'repair_content'     => ['nullable', 'string'],
            'repair_status'      => ['nullable', 'string', Rule::in(VaRepairStatus::label_keys())],
            'factory_out_dt'     => ['nullable', 'date'],
            'settlement_status'  => ['nullable', 'string', Rule::in(VaSettlementStatus::label_keys())],
            'pickup_status'      => ['nullable', 'string', Rule::in(VaPickupStatus::label_keys())],
            'settlement_method'  => ['nullable', 'string', Rule::in(VaSettlementMethod::label_keys())],
            'managed_vehicle'    => ['nullable', 'string', Rule::in(VaManagedVehicle::label_keys())],
            'va_remark'          => ['nullable', 'string'],
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
            $vehicleAccident = VehicleAccident::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function vaClaimStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('va_claim_status')?->label
        );
    }

    protected function vaRepairStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('va_repair_status')?->label
        );
    }

    protected function vaSettlementStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('va_settlement_status')?->label
        );
    }

    protected function vaPickupStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('va_pickup_status')?->label
        );
    }

    protected function vaSettlementMethodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('va_settlement_method')?->label
        );
    }

    protected function vaManagedVehicleLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('va_managed_vehicle')?->label
        );
    }

    protected function vaAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }

    protected function vaAccidentInfo(): Attribute
    {
        return $this->arrayInfo();
    }
}
