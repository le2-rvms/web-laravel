<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Vehicle\VmvStatus;
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

#[ClassName('手动违章', '记录')]
#[ColumnDesc('vmv_id')]
#[ColumnDesc('ve_id')]
#[ColumnDesc('plate_no', required: true)]
#[ColumnDesc('vu_id')]
#[ColumnDesc('violation_datetime', type: ColumnType::DATETIME, required: true)]
#[ColumnDesc('violation_content')]
#[ColumnDesc('location', required: true)]
#[ColumnDesc('fine_amount', required: true)]
#[ColumnDesc('penalty_points', required: true)]
#[ColumnDesc('status', required: true, enum_class: VmvStatus::class)]
#[ColumnDesc('vmv_remark')]
/**
 * @property int           $vmv_id             违章序号
 * @property int           $ve_id              车辆序号；指向车辆表
 * @property null|int      $vu_id              车辆使用时间段序号
 * @property null|Carbon   $violation_datetime 违章发生日时
 * @property null|string   $violation_content  违章内容
 * @property null|string   $location           违章发生地点
 * @property null|float    $fine_amount        违章罚款金额
 * @property null|int      $penalty_points     违章扣分
 * @property int|VmvStatus $status             违章状态；例已处理、未处理
 * @property null|string   $vmv_remark         违章备注
 */
class VehicleManualViolation extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'vmv_id';

    protected $guarded = ['vmv_id'];

    protected $casts = [
        'status'             => VmvStatus::class,
        'violation_datetime' => 'datetime:Y-m-d H:i',
    ];

    protected $appends = [
        'status_label',
        'vehicle_usages_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id');
    }

    public function VehicleUsage(): BelongsTo
    {
        return $this->BelongsTo(VehicleUsage::class, 'vu_id', 'vu_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $so_id = $search['so_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('vehicle_manual_violations', 'vmv')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vmv.ve_id')
            ->leftJoin('vehicle_usages as vu', 'vu.vu_id', '=', 'vmv.vu_id')
            ->leftJoin('sale_orders as so', 'so.so_id', '=', 'vu.so_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vmv.ve_id', '=', $ve_id);
            })
            ->when($so_id, function (Builder $query) use ($so_id) {
                $query->where('vu.so_id', '=', $so_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('so.cu_id', '=', $cu_id);
            })
            ->when(
                null === $ve_id && null === $so_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vmv.vmv_id');
                },
                function (Builder $query) {
                    $query->orderBy('vmv.vmv_id');
                }
            )
            ->select('vmv.*', 've.plate_no')
            ->addSelect(
                DB::raw(VmvStatus::toCaseSQL()),
                DB::raw(VmvStatus::toColorSQL()),
                DB::raw("to_char(violation_datetime, 'YYYY-MM-DD HH24:MI') as violation_datetime_"),
            )
        ;
    }

    public static function indexStat($list): bool
    {
        return count($list) > 0;
    }

    public static function importColumns(): array
    {
        return [
            'plate_no'           => [VehicleManualViolation::class, 'plate_no'],
            'violation_datetime' => [VehicleManualViolation::class, 'violation_datetime'],
            'location'           => [VehicleManualViolation::class, 'location'],
            'violation_content'  => [VehicleManualViolation::class, 'violation_content'],
            'fine_amount'        => [VehicleManualViolation::class, 'fine_amount'],
            'penalty_points'     => [VehicleManualViolation::class, 'penalty_points'],
            'status'             => [VehicleManualViolation::class, 'status'],
            'vmv_remark'         => [VehicleManualViolation::class, 'vmv_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['ve_id']  = Vehicle::plateNoKv($item['plate_no'] ?? null);
            $item['status'] = VmvStatus::searchValue($item['status'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules
            = [
                've_id'              => ['required', 'integer'],
                'violation_datetime' => ['required', 'date'],
                'violation_content'  => ['nullable', 'string', 'max:200'],
                'location'           => ['nullable', 'string', 'max:255'],
                'fine_amount'        => ['nullable', 'numeric'],
                'penalty_points'     => ['nullable', 'integer'],
                'status'             => ['required', 'integer', Rule::in(VmvStatus::label_keys())],
                'vmv_remark'         => ['nullable', 'string'],
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
            $vehicleManualViolation = VehicleManualViolation::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('status')?->label
        );
    }

    protected function vehicleUsagesLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => null === $this->getOriginal('vu_id') ? '未匹配' : '已匹配'
        );
    }
}
