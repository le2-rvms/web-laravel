<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Vehicle\VePendingStatusRental;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VeVeType;
use App\Exceptions\ClientException;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Admin\Staff;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

#[ClassName('车辆', '信息')]
#[ColumnDesc('plate_no', required: true, unique: true)]
#[ColumnDesc('ve_type', required: false, enum_class: VeVeType::class)]
#[ColumnDesc('vm_id', required: true, desc: '通过[车型信息管理]添加的序号，文本格式')]
#[ColumnDesc('ve_license_owner')]
#[ColumnDesc('ve_license_address')]
#[ColumnDesc('ve_license_usage')]
#[ColumnDesc('ve_license_type')]
#[ColumnDesc('ve_license_company')]
#[ColumnDesc('ve_license_vin_code')]
#[ColumnDesc('ve_license_engine_no')]
#[ColumnDesc('ve_license_purchase_date', type: ColumnType::DATE)]
#[ColumnDesc('ve_license_valid_until_date', type: ColumnType::DATE)]
#[ColumnDesc('ve_mileage')]
#[ColumnDesc('ve_color')]
#[ColumnDesc('ve_cert_no')]
#[ColumnDesc('ve_cert_valid_to', type: ColumnType::DATE)]
#[ColumnDesc('ve_remark')]
/**
 * @property int                          $ve_id                       车辆序号
 * @property string                       $plate_no                    车牌号
 * @property string|VeVeType              $ve_type                     车辆类型
 * @property null|int                     $vm_id                       车型序号；表示车辆属于哪种车型
 * @property mixed|VeStatusService        $status_service              运营状态
 * @property string|VeStatusRental        $status_rental               租赁状态；
 * @property string|VePendingStatusRental $pending_status_rental       待租赁状态；
 * @property mixed|VeStatusDispatch       $status_dispatch             是否发车；例如未发车或已发车
 * @property mixed                        $status_accident__           ；出险状态；例如无出险或发生事故 //todo
 * @property mixed                        $status_maintenance__        ；保养状态；例如无需保养、保养中
 * @property null|mixed                   $ve_license_face_photo       行驶证照片
 * @property null|mixed                   $ve_license_back_photo       行驶证背面照片
 * @property null|string                  $ve_license_owner            车辆所有人
 * @property null|string                  $ve_license_address          车辆所有人住所
 * @property null|string                  $ve_license_usage            车辆使用性质；例如私用、公用等
 * @property null|string                  $ve_license_type             车辆类型；例如轿车、SUV等
 * @property null|string                  $ve_license_company          车辆所属公司名称
 * @property null|string                  $ve_license_vin_code         车辆识别号码；唯一标识每辆车
 * @property null|string                  $ve_license_engine_no        发动机编号；唯一标识车辆的发动机
 * @property null|Carbon                  $ve_license_purchase_date    车辆购置日期
 * @property null|Carbon                  $ve_license_valid_until_date 检验有效期至
 * @property null|int                     $ve_mileage                  车辆当前总行驶公里数
 * @property null|string                  $ve_color                    车辆颜色
 * @property null|string                  $vehicle_manager             负责车管
 * @property null|string                  $ve_cert_no                  车证号
 * @property null|array<string>           $ve_cert_photo               车证照片
 * @property null|Carbon                  $ve_cert_valid_to            车证到期日期
 * @property null|array<array<string>>    $ve_additional_photos        车辆附加照片
 * @property null|string                  $ve_remark                   车辆备注
 *                                                                     -
 * @property VehicleModel                 $VehicleModel
 * @property Staff                        $VehicleManager
 *                                                                     -
 * @property string|VeVeType              $ve_type_label               车辆类型-中文
 * @property null|string                  $vehicle_brand_model_name    车牌品牌车型
 * @property null|string                  $status_service_label        运营状态-中文
 * @property null|string                  $status_repair_label         维修状态-中文
 * @property null|string                  $status_rental_label         租车状态-中文
 * @property null|string                  $status_dispatch_label       是否发车状态-中文
 */
class Vehicle extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 've_id';

    protected $guarded = ['ve_id'];

    protected $appends = [
        've_type_label',
        'vehicle_brand_model_name',
        'status_service_label',
        'status_repair_label',
        'status_rental_label',
        'status_dispatch_label',
    ];

    protected $attributes = [
        'status_service'  => VeStatusService::YES,
        'status_rental'   => VeStatusRental::PENDING,
        'status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
    ];

    protected $casts = [
        've_type'         => VeVeType::class,
        'status_service'  => VeStatusService::class,
        'status_rental'   => VeStatusRental::class,
        'status_dispatch' => VeStatusDispatch::class,
    ];

    public function VehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vm_id', 'vm_id');
    }

    public function VehicleUsages(): HasMany
    {
        return $this->hasMany(VehicleUsage::class, 've_id', 've_id');
    }

    public function VehiclePreparations(): HasMany
    {
        return $this->hasMany(VehiclePreparation::class, 've_id', 've_id');
    }

    public function VehicleRepairs(): HasMany
    {
        return $this->hasMany(VehicleRepair::class, 've_id', 've_id');
    }

    public function VehicleMaintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class, 've_id', 've_id');
    }

    public function VehicleInsurances(): HasMany
    {
        return $this->hasMany(VehicleInsurance::class, 've_id', 've_id');
    }

    public function VehicleManager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'vehicle_manager', 'id');
    }

    public function scopeOnService(Builder $query): void
    {
        $query->where('status_service', VeStatusService::YES);
    }

    public function updateStatus(
        ?string $status_service = null,
        ?string $status_rental = null,
        ?string $status_dispatch = null,
    ): bool {
        $update = [];
        if ($status_service) {
            $update['status_service'] = $status_service;
        }
        if ($status_rental) {
            $update['status_rental'] = $status_rental;
        }
        if ($status_dispatch) {
            $update['status_dispatch'] = $status_dispatch;
        }

        return $this->update($update);
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class())
            .'Options';

        $value = DB::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', function ($join) {
                $join->on('vm.vm_id', '=', 've.vm_id');
            })
            ->where('ve.status_service', VeStatusService::YES)
            ->where($where)
            ->select(DB::raw("CONCAT(ve.plate_no,'-',COALESCE(vm.brand_name,'未知品牌'),'-', COALESCE(vm.model_name,'未知车型')) as text,ve.ve_id as value"))
            ->get()
        ;

        return [$key => $value];
    }

    public static function optionsNo(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class())
            .'Options';

        $value = DB::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', function ($join) {
                $join->on('vm.vm_id', '=', 've.vm_id');
            })
            ->where('ve.status_service', VeStatusService::YES)
            ->select(DB::raw("CONCAT(ve.plate_no,'-',COALESCE(vm.brand_name,'未知品牌'),'-', COALESCE(vm.model_name,'未知车型')) as text,ve.plate_no as value"))
            ->get()
        ;

        return [$key => $value];
    }

    public function check_status(int $statusService, array $statusRentals, array $statusDispatches, Validator $validator): bool
    {
        if ($statusService !== $this->status_service->value) {
            $validator->errors()->add(
                $key     = 've_id',
                $message = '车辆:'.$this->plate_no.'的运营状态不应该为：'.$this->status_service->label
            );

            return false;
        }

        if ($statusRentals && !in_array($this->status_rental->value, $statusRentals)) {
            $validator->errors()->add(
                $key     = 've_id',
                $message = '车辆:'.$this->plate_no.'的租车状态不应该为：'.$this->status_rental->label
            );

            return false;
        }

        if ($statusDispatches && !in_array($this->status_dispatch->value, $statusDispatches)) {
            $validator->errors()->add(
                $key     = 've_id',
                $message = '车辆:'.$this->plate_no.'的发车状态为：'.$this->status_dispatch->label
            );

            return false;
        }

        return true;
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', 've.vm_id', '=', 'vm.vm_id')
            ->select('ve.*', 'vm.brand_name', 'vm.model_name')
            ->addSelect(
                DB::raw(VeStatusService::toCaseSQL()),
                DB::raw(VeStatusService::toColorSQL()),
                DB::raw(VeStatusRental::toCaseSQL()),
                DB::raw(VeStatusDispatch::toCaseSQL()),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Vehicle.ve_id'                    => fn ($item) => $item->ve_id,
            'Vehicle.plate_no'                 => fn ($item) => $item->plate_no,
            'Vehicle.ve_type'                  => fn ($item) => $item->ve_type,
            'VehicleModel.brand_model'         => fn ($item) => $item->brand_name.'-'.$item->model_name,
            'Vehicle.ve_license_owner'         => fn ($item) => $item->ve_license_owner,
            'Vehicle.ve_license_usage'         => fn ($item) => $item->ve_license_usage,
            'Vehicle.ve_license_type'          => fn ($item) => $item->ve_license_type,
            'Vehicle.ve_license_purchase_date' => fn ($item) => $item->ve_license_purchase_date,
            'Vehicle.status_service'           => fn ($item) => $item->status_service_label,
        ];
    }

    public static function plateNoKv(?string $plate_no = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('vehicles')
                ->select('ve_id', 'plate_no')
                ->pluck('ve_id', 'plate_no')
                ->toArray()
            ;
        }

        if ($plate_no) {
            return $kv[$plate_no] ?? null;
        }

        return $kv;
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'                    => [Vehicle::class, 'plate_no'],
            've_type'                     => [Vehicle::class, 've_type'],
            'vm_id'                       => [Vehicle::class, 'vm_id'],
            've_license_owner'            => [Vehicle::class, 've_license_owner'],
            've_license_address'          => [Vehicle::class, 've_license_address'],
            've_license_usage'            => [Vehicle::class, 've_license_usage'],
            've_license_type'             => [Vehicle::class, 've_license_type'],
            've_license_company'          => [Vehicle::class, 've_license_company'],
            've_license_vin_code'         => [Vehicle::class, 've_license_vin_code'],
            've_license_engine_no'        => [Vehicle::class, 've_license_engine_no'],
            've_license_purchase_date'    => [Vehicle::class, 've_license_purchase_date'],
            've_license_valid_until_date' => [Vehicle::class, 've_license_valid_until_date'],
            've_mileage'                  => [Vehicle::class, 've_mileage'],
            've_color'                    => [Vehicle::class, 've_color'],
            've_cert_no'                  => [Vehicle::class, 've_cert_no'],
            've_cert_valid_to'            => [Vehicle::class, 've_cert_valid_to'],
            've_remark'                   => [Vehicle::class, 've_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_type']              = VeVeType::searchValue($item['ve_type']);
            static::$fields['plate_no'][] = $item['plate_no'];
            static::$fields['vm_id'][]    = $item['vm_id'];
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'plate_no'                    => ['required', 'string', 'max:64'],
            've_type'                     => ['nullable', 'string', Rule::in(VeVeType::label_keys())],
            'vm_id'                       => ['nullable', 'integer'],
            've_license_owner'            => ['nullable', 'string', 'max:100'],
            've_license_address'          => ['nullable', 'string', 'max:255'],
            've_license_usage'            => ['nullable', 'string', 'max:50'],
            've_license_type'             => ['nullable', 'string', 'max:50'],
            've_license_company'          => ['nullable', 'string', 'max:100'],
            've_license_vin_code'         => ['nullable', 'string', 'max:50'],
            've_license_engine_no'        => ['nullable', 'string', 'max:50'],
            've_license_purchase_date'    => ['nullable', 'date'],
            've_license_valid_until_date' => ['nullable', 'date', 'after:ve_license_purchase_date'],
            've_mileage'                  => ['nullable', 'integer'],
            've_color'                    => ['nullable', 'string', 'max:30'],
            've_cert_no'                  => ['nullable', 'string', 'max:50'],
            've_cert_valid_to'            => ['nullable', 'date'],
            've_remark'                   => ['nullable', 'string', 'max:255'],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // plate_no
            $plate_no = Vehicle::query()->whereIn('plate_no', static::$fields['plate_no'])->pluck('plate_no')->toArray();
            if (count($plate_no) > 0) {
                throw new ClientException('以下车牌号已经存在：'.join(',', $plate_no));
            }
            // vm_id
            $missing = array_diff(static::$fields['vm_id'], VehicleModel::query()->pluck('vm_id')->toArray());
            if (count($missing) > 0) {
                throw new ClientException('以下车型序列号不存在：'.join(',', $missing));
            }
        };
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $vehicle = Vehicle::query()->create($input);
        };
    }

    protected function vehicleBrandModelName(): Attribute
    {
        return Attribute::make(
            get: fn () => join(' | ', [
                $this->plate_no,
                $this->VehicleModel?->brand_name ?? '未知品牌',
                $this->VehicleModel?->model_name ?? '未知车型',
            ])
        );
    }

    protected function statusRentalLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('status_rental')?->label
        );
    }

    protected function statusDispatchLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('status_dispatch')?->label
        );
    }

    protected function statusServiceLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('status_service')?->label
        );
    }

    protected function statusRepairLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('status_repair')?->label
        );
    }

    protected function veTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('ve_type')?->label
        );
    }

    protected function veLicenseFacePhoto(): Attribute
    {
        return $this->uploadFile();
    }

    protected function veLicenseBackPhoto(): Attribute
    {
        return $this->uploadFile();
    }

    protected function veCertPhoto(): Attribute
    {
        return $this->uploadFile();
    }

    protected function veAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
