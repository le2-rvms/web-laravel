<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VsInspectionType;
use App\Models\_\Configuration;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('车辆年检', '记录')]
#[ColumnDesc('vs_inspection_type', required: true, enum_class: VsInspectionType::class)]
#[ColumnDesc('vs_inspector', required: true)]
#[ColumnDesc('vs_inspection_date', required: true)]
#[ColumnDesc('vs_inspection_amount', required: true)]
#[ColumnDesc('vs_next_inspection_date', required: true)]
#[ColumnDesc('vs_remark')]
/**
 * @property int                     $vs_id                         年检记录序号
 * @property string|VsInspectionType $vs_inspection_type            年检类型
 * @property int                     $vs_ve_id                      车辆序号
 * @property string                  $vs_inspector                  年检处理人
 * @property Carbon                  $vs_inspection_date            年检日期
 * @property float                   $vs_inspection_amount          年检金额
 * @property Carbon|string           $vs_next_inspection_date       下次年检日期
 * @property null|mixed              $vs_additional_photos          附加照片;存储照片路径的JSON数组
 * @property null|string             $vs_remark                     年检备注
 * @property null|string             $vs_vehicle_last_date          最后一次车辆年检时间
 * @property null|string             $vs_gas_cylinder_last_date     最后一次气罐年检时间
 * @property null|string             $vs_certificate_last_date      最后一次车证年检时间
 * @property null|string             $vs_business_license_last_date 最后一次营业执照年检时间
 * @property Vehicle                 $Vehicle
 */
class VehicleSchedule extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'vs_created_at';
    public const UPDATED_AT = 'vs_updated_at';
    public const UPDATED_BY = 'vs_updated_by';

    protected $primaryKey = 'vs_id';

    protected $guarded = ['vs_id'];

    protected $attributes = [];

    protected $casts = [
        'vs_inspection_date'      => 'date:Y-m-d',
        'vs_next_inspection_date' => 'date:Y-m-d',
        'vs_maintenance_amount'   => 'decimal:2',
        'vs_inspection_type'      => VsInspectionType::class,
    ];

    protected $appends = [
        'vs_inspection_type_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vs_ve_id', 've_id')->with('VehicleModel');
    }

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->from('vehicle_schedules', 'vs')
            ->joinSub(
                // 直接在 joinSub 中定义子查询
                DB::table('vehicle_schedules')
                    ->select('vs_ve_id', 'vs_inspection_type', DB::raw('MAX(vs_next_inspection_date) as max_vs_next_inspection_date'))
                    ->groupBy('vs_ve_id', 'vs_inspection_type'),
                'p2',
                function ($join) {
                    $join->on('vs.vs_ve_id', '=', 'p2.vs_ve_id')
                        ->on('vs.vs_inspection_type', '=', 'p2.vs_inspection_type')
                        ->on('vs.vs_next_inspection_date', '=', 'p2.max_vs_next_inspection_date')
                    ;
                }
            )
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vs.vs_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->whereRaw(
                'EXTRACT(EPOCH FROM now() - vs.vs_next_inspection_date) / 86400.0 >= ?',
                ['-'.Configuration::fetch('risk.vs_interval_day.less')]
            )
            ->orderByDesc('vs.vs_next_inspection_date')
            ->select('vs.*', 've.ve_plate_no', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->addSelect(
                DB::raw(VsInspectionType::toCaseSQL()),
                DB::raw('CAST(EXTRACT(EPOCH FROM now() - vs.vs_next_inspection_date) / 86400.0 AS INTEGER) as vs_interval_day')
            )
        ;
    }

    public static function detailQuery(): Builder
    {
        return DB::query()
            ->from('vehicle_schedules', 'vs')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vs.vs_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->select('vs.*', 've.ve_plate_no', 'vm.vm_brand_name', 'vm.vm_model_name')
            ->addSelect(
                DB::raw(VsInspectionType::toCaseSQL()),
            )
            ->orderBy('vs.vs_id')
        ;
    }

    public static function importColumns(): array
    {
        return [
            'vs_inspection_type'      => [VehicleSchedule::class, 'vs_inspection_type'],
            'vs_plate_no'             => [Vehicle::class, 've_plate_no'],
            'vs_inspector'            => [VehicleSchedule::class, 'vs_inspector'],
            'vs_inspection_date'      => [VehicleSchedule::class, 'vs_inspection_date'],
            'vs_inspection_amount'    => [VehicleSchedule::class, 'vs_inspection_amount'],
            'vs_next_inspection_date' => [VehicleSchedule::class, 'vs_next_inspection_date'],
            'vs_remark'               => [VehicleSchedule::class, 'vs_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['vs_inspection_type'] = VsInspectionType::searchValue($item['vs_inspection_type'] ?? null);
            $item['vs_ve_id']           = Vehicle::plateNoKv($item['vs_plate_no'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'vs_inspection_type'      => ['required', 'string', Rule::in(VsInspectionType::label_keys())],
            'vs_ve_id'                => ['required', 'integer'],
            'vs_inspector'            => ['required', 'string', 'max:255'],
            'vs_inspection_date'      => ['required', 'date'],
            'vs_next_inspection_date' => ['required', 'date', 'after:inspection_date'],
            'vs_inspection_amount'    => ['required', 'decimal:0,2', 'gte:0'],
            'vs_remark'               => ['nullable', 'string'],
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
            $vehicleSchedule = VehicleSchedule::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    public static function stQuery(array $search = []): Builder
    {
        $subSql = "
SELECT
  vs_ve_id,
  MAX(vs_inspection_date) FILTER (WHERE vs_inspection_type = 'vehicle')       AS vs_vehicle_last_date,
  MAX(vs_inspection_date) FILTER (WHERE vs_inspection_type = 'gas_cylinder')  AS vs_gas_cylinder_last_date,
  MAX(vs_inspection_date) FILTER (WHERE vs_inspection_type = 'certificate')   AS vs_certificate_last_date,
  MAX(vs_inspection_date) FILTER (WHERE vs_inspection_type = 'business_license')   AS vs_business_license_last_date
FROM vehicle_schedules
GROUP BY vs_ve_id
";

        return DB::query()
            ->from('vehicles', 've')
            ->leftJoin('vehicle_models as vm', 've.ve_vm_id', '=', 'vm.vm_id')
            ->leftJoinSub($subSql, 'vs', 'vs.vs_ve_id', '=', 've.ve_id')
            ->select('ve.*', 'vm.vm_brand_name', 'vm.vm_model_name', 'vs.vs_vehicle_last_date', 'vs.vs_gas_cylinder_last_date', 'vs.vs_certificate_last_date', 'vs.vs_business_license_last_date')
            ->orderBy('ve.ve_id', 'desc')
            ->where('ve.ve_status_service', '=', VeStatusService::YES)
        ;
    }

    public static function stColumns(): array
    {
        return [
            'Vehicle.ve_id'                              => fn ($item) => $item->ve_id,
            'Vehicle.plate_no'                           => fn ($item) => $item->plate_no,
            'VehicleModel.brand_model'                   => fn ($item) => $item->vm_brand_name.'-'.$item->vm_model_name,
            'Vehicle.ve_license_owner'                   => fn ($item) => $item->ve_license_owner,
            'Vehicle.ve_license_vin_code'                => fn ($item) => $item->ve_license_vin_code,
            'Vehicle.ve_license_engine_no'               => fn ($item) => $item->ve_license_engine_no,
            'VehicleSchedule.vehicle_last_date'          => fn ($item) => $item->vehicle_last_date,
            'VehicleSchedule.gas_cylinder_last_date'     => fn ($item) => $item->gas_cylinder_last_date,
            'VehicleSchedule.certificate_last_date'      => fn ($item) => $item->certificate_last_date,
            'VehicleSchedule.business_license_last_date' => fn ($item) => $item->business_license_last_date,
        ];
    }

    protected function vsInspectionTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vs_inspection_type')?->label
        );
    }

    protected function vsAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
