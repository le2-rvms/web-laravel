<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Vehicle\VePendingStatusRental;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VeType;
use App\Exceptions\ClientException;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminTeam;
use App\Models\One\OneAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

#[ClassName('车辆', '信息')]
#[ColumnDesc('ve_plate_no', required: true, unique: true)]
#[ColumnDesc('ve_type', required: false, enum_class: VeType::class)]
#[ColumnDesc('ve_vm_id', required: false, desc: '通过[车型信息管理]添加的序号，文本格式')]
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
#[ColumnDesc('ve_team_id')]
#[ColumnDesc('ve_cert_valid_to', type: ColumnType::DATE)]
#[ColumnDesc('ve_remark')]
#[ColumnDesc('ve_oa_id')]
/**
 * @property int                          $ve_id                       车辆序号
 * @property string                       $ve_plate_no                 车牌号
 * @property string|VeType                $ve_type                     车辆类型
 * @property null|int                     $ve_vm_id                    车型序号；表示车辆属于哪种车型
 * @property string|VeStatusService       $ve_status_service           运营状态
 * @property string|VeStatusRental        $ve_status_rental            租赁状态；
 * @property string|VePendingStatusRental $ve_pending_status_rental    待租赁状态；
 * @property string|VeStatusDispatch      $ve_status_dispatch          是否发车；例如未发车或已发车
 * @property null|array                   $ve_license_face_photo       行驶证照片
 * @property null|array                   $ve_license_back_photo       行驶证背面照片
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
 * @property null|int                     $ve_oa_id                    查违章账号序号
 * @property null|string                  $ve_vehicle_manager          负责车管
 * @property null|int                     $ve_team_id                  所属车队
 * @property null|string                  $ve_cert_no                  车证号
 * @property null|array<string>           $ve_cert_photo               车证照片
 * @property null|Carbon                  $ve_cert_valid_to            车证到期日期
 * @property null|array<array<string>>    $ve_additional_photos        车辆附加照片
 * @property null|string                  $ve_remark                   车辆备注
 *                                                                     -
 * @property VehicleModel                 $VehicleModel
 * @property Admin                        $VehicleManager
 * @property null|OneAccount              $ViolationAccount
 *                                                                     -
 * @property string|VeType                $ve_type_label               车辆类型-中文
 * @property null|string                  $ve_brand_model_name         车牌品牌车型
 * @property null|string                  $ve_status_service_label     运营状态-中文
 * @property null|string                  $ve_status_repair_label      维修状态-中文
 * @property null|string                  $ve_status_rental_label      租车状态-中文
 * @property null|string                  $ve_status_dispatch_label    是否发车状态-中文
 */
class Vehicle extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 've_created_at';
    public const UPDATED_AT = 've_updated_at';
    public const UPDATED_BY = 've_updated_by';

    protected $primaryKey = 've_id';

    protected $guarded = ['ve_id'];

    protected $appends = [
        've_type_label',
        've_brand_model_name',
        've_status_service_label',
        've_status_repair_label',
        've_status_rental_label',
        've_status_dispatch_label',
    ];

    protected $attributes = [
        've_status_service'  => VeStatusService::YES,
        've_status_rental'   => VeStatusRental::PENDING,
        've_status_dispatch' => VeStatusDispatch::NOT_DISPATCHED,
    ];

    protected $casts = [
        've_type'            => VeType::class,
        've_status_service'  => VeStatusService::class,
        've_status_rental'   => VeStatusRental::class,
        've_status_dispatch' => VeStatusDispatch::class,
        've_team_id'         => 'integer',
        've_oa_id'           => 'integer',
    ];

    public function VehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 've_vm_id', 'vm_id');
    }

    public function VehicleUsages(): HasMany
    {
        return $this->hasMany(VehicleUsage::class, 'vu_ve_id', 've_id');
    }

    public function VehiclePreparations(): HasMany
    {
        return $this->hasMany(VehiclePreparation::class, 'vp_ve_id', 've_id');
    }

    public function VehicleRepairs(): HasMany
    {
        return $this->hasMany(VehicleRepair::class, 'vr_ve_id', 've_id');
    }

    public function VehicleMaintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class, 'vm_ve_id', 've_id');
    }

    public function VehicleInsurances(): HasMany
    {
        return $this->hasMany(VehicleInsurance::class, 'vi_ve_id', 've_id');
    }

    public function VehicleManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 've_vehicle_manager', 'id');
    }

    public function Team(): BelongsTo
    {
        return $this->belongsTo(AdminTeam::class, 've_team_id', 'at_id');
    }

    public function ViolationAccount(): BelongsTo
    {
        return $this->belongsTo(OneAccount::class, 've_oa_id', 'oa_id');
    }

    public function scopeOnService(Builder $query): void
    {
        $query->where('ve_status_service', VeStatusService::YES);
    }

    public function updateStatus(
        ?string $ve_status_service = null,
        ?string $ve_status_rental = null,
        ?string $ve_status_dispatch = null,
    ): bool {
        $update = [];
        if ($ve_status_service) {
            $update['ve_status_service'] = $ve_status_service;
        }
        if ($ve_status_rental) {
            $update['ve_status_rental'] = $ve_status_rental;
        }
        if ($ve_status_dispatch) {
            $update['ve_status_dispatch'] = $ve_status_dispatch;
        }

        return $this->update($update);
    }

    public static function optionsQuery(): Builder
    {
        return static::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', function ($join) {
                $join->on('vm.vm_id', '=', 've.ve_vm_id');
            })
            ->where('ve.ve_status_service', VeStatusService::YES)
            ->select(DB::raw("CONCAT(ve.ve_plate_no,'-',COALESCE(vm.vm_brand_name,'未知品牌'),'-', COALESCE(vm.vm_model_name,'未知车型')) as text,ve.ve_id as value"))
        ;
    }

    public static function optionsNo(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $value = static::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', function ($join) {
                $join->on('vm.vm_id', '=', 've.ve_vm_id');
            })
            ->where('ve.ve_status_service', VeStatusService::YES)
            ->select(DB::raw("CONCAT(ve.ve_plate_no,'-',COALESCE(vm.vm_brand_name,'未知品牌'),'-', COALESCE(vm.vm_model_name,'未知车型')) as text,ve.ve_plate_no as value"))
            ->get()
        ;

        return [$key => $value];
    }

    public function check_status(int $statusService, array $statusRentals, array $statusDispatches, Validator $validator): bool
    {
        if ($statusService !== $this->ve_status_service->value) {
            $validator->errors()->add(
                $key     = 've_id',
                $message = '车辆:'.$this->ve_plate_no.'的运营状态不应该为：'.$this->ve_status_service->label
            );

            return false;
        }

        if ($statusRentals && !in_array($this->ve_status_rental->value, $statusRentals)) {
            $validator->errors()->add(
                $key     = 've_id',
                $message = '车辆:'.$this->ve_plate_no.'的租车状态不应该为：'.$this->ve_status_rental->label
            );

            return false;
        }

        if ($statusDispatches && !in_array($this->ve_status_dispatch->value, $statusDispatches)) {
            $validator->errors()->add(
                $key     = 've_id',
                $message = '车辆:'.$this->ve_plate_no.'的发车状态为：'.$this->ve_status_dispatch->label
            );

            return false;
        }

        return true;
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', 've.ve_vm_id', '=', 'vm.vm_id')
            ->leftJoin('admins as a', 've.ve_vehicle_manager', '=', 'a.id')
            ->leftJoin('admin_teams as at', 've.ve_team_id', '=', 'at.at_id')
            ->leftJoin('one_accounts as oa', 've.ve_oa_id', '=', 'oa.oa_id')
            ->select('ve.*', 'vm.*', 'a.name as adm_vehicle_manager_name', 'at.at_name', 'oa.oa_name')
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
            'Vehicle.ve_plate_no'              => fn ($item) => $item->ve_plate_no,
            'Vehicle.ve_type'                  => fn ($item) => $item->ve_type,
            'VehicleModel.ve_brand_model'      => fn ($item) => $item->ve_brand_name.'-'.$item->ve_model_name,
            'Vehicle.ve_license_owner'         => fn ($item) => $item->ve_license_owner,
            'Vehicle.ve_license_usage'         => fn ($item) => $item->ve_license_usage,
            'Vehicle.ve_license_type'          => fn ($item) => $item->ve_license_type,
            'Vehicle.ve_license_purchase_date' => fn ($item) => $item->ve_license_purchase_date,
            'Vehicle.ve_status_service'        => fn ($item) => $item->ve_status_service_label,
            'AdminTeam.at_name'                => fn ($item) => $item->at_name,
            'OneAccount.oa_name'               => fn ($item) => $item->oa_name,
        ];
    }

    public static function plateNoKv(?string $plate_no = null)
    {
        static $kv = null;

        if (null === $kv) {
            $kv = DB::query()
                ->from('vehicles')
                ->select('ve_id', 've_plate_no')
                ->pluck('ve_id', 've_plate_no')
                ->toArray()
            ;
        }

        return $kv[$plate_no] ?? null;
    }

    public static function importColumns(): array
    {
        return [
            've_plate_no'                 => [Vehicle::class, 've_plate_no'],
            've_type'                     => [Vehicle::class, 've_type'],
            've_vm_id'                    => [Vehicle::class, 've_vm_id'],
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
            've_team_id'                  => [Vehicle::class, 've_team_id'],
            've_cert_no'                  => [Vehicle::class, 've_cert_no'],
            've_cert_valid_to'            => [Vehicle::class, 've_cert_valid_to'],
            've_remark'                   => [Vehicle::class, 've_remark'],
            've_oa_id'                    => [Vehicle::class, 've_oa_id'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_type']                 = VeType::searchValue($item['ve_type'] ?? null);
            static::$fields['ve_plate_no'][] = $item['ve_plate_no'];
            static::$fields['ve_vm_id'][]    = $item['ve_vm_id'] ?? null;
            $item['ve_team_id']              = AdminTeam::nameValue($item['ve_team_id'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            've_plate_no'                 => ['required', 'string', 'max:64'],
            've_type'                     => ['nullable', 'string', Rule::in(VeType::label_keys())],
            've_vm_id'                    => ['nullable', 'integer'],
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
            've_team_id'                  => ['nullable', 'integer', Rule::exists(AdminTeam::class, 'at_id')],
            've_cert_no'                  => ['nullable', 'string', 'max:50'],
            've_cert_valid_to'            => ['nullable', 'date'],
            've_remark'                   => ['nullable', 'string', 'max:255'],
            've_oa_id'                    => ['nullable', 'integer', Rule::exists(OneAccount::class, 'oa_id')],
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
            $plate_no = Vehicle::query()->whereIn('ve_plate_no', static::$fields['ve_plate_no'])->pluck('ve_plate_no')->toArray();
            if (count($plate_no) > 0) {
                throw new ClientException('以下车牌号已经存在：'.join(',', $plate_no));
            }
            // vm_id
            static::$fields['ve_vm_id'] = array_filter(static::$fields['ve_vm_id']);
            $missing                    = array_diff(static::$fields['ve_vm_id'], VehicleModel::query()->pluck('vm_id')->toArray());
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

    protected function veBrandModelName(): Attribute
    {
        return Attribute::make(
            get: fn () => join(' | ', [
                $this->ve_plate_no,
                $this->VehicleModel?->vm_brand_name ?? '未知品牌',
                $this->VehicleModel?->vm_model_name ?? '未知车型',
            ])
        );
    }

    protected function veStatusRentalLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('ve_status_rental')?->label
        );
    }

    protected function veStatusDispatchLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('ve_status_dispatch')?->label
        );
    }

    protected function veStatusServiceLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('ve_status_service')?->label
        );
    }

    protected function veStatusRepairLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('ve_status_repair')?->label
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
