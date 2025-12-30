<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\VehicleManualViolation\VvStatus;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('手动违章', '记录')]
#[ColumnDesc('vv_id')]
#[ColumnDesc('vv_ve_id')]
#[ColumnDesc('vv_plate_no', required: true)]
#[ColumnDesc('vv_vu_id')]
#[ColumnDesc('vv_violation_datetime', type: ColumnType::DATETIME, required: true)]
#[ColumnDesc('vv_violation_content')]
#[ColumnDesc('vv_location', required: true)]
#[ColumnDesc('vv_fine_amount', required: true)]
#[ColumnDesc('vv_penalty_points', required: true)]
#[ColumnDesc('vv_status', required: true, enum_class: VvStatus::class)]
#[ColumnDesc('vv_remark')]
/**
 * @property int          $vv_id                 违章序号
 * @property int          $vv_ve_id              车辆序号；指向车辆表
 * @property null|int     $vv_vu_id              车辆使用时间段序号
 * @property null|Carbon  $vv_violation_datetime 违章发生日时
 * @property null|string  $vv_violation_content  违章内容
 * @property null|string  $vv_location           违章发生地点
 * @property null|float   $vv_fine_amount        违章罚款金额
 * @property null|int     $vv_penalty_points     违章扣分
 * @property int|VvStatus $vv_status             违章状态；例已处理、未处理
 * @property null|string  $vv_remark             违章备注
 */
class VehicleManualViolation extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'vv_created_at';
    public const UPDATED_AT = 'vv_updated_at';
    public const UPDATED_BY = 'vv_updated_by';

    protected $primaryKey = 'vv_id';

    protected $guarded = ['vv_id'];

    protected $casts = [
        'vv_status'             => VvStatus::class,
        'vv_violation_datetime' => 'datetime:Y-m-d H:i',
    ];

    protected $appends = [
        'vv_status_label',
        'vv_vehicle_usages_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vv_ve_id', 've_id');
    }

    public function VehicleUsage(): BelongsTo
    {
        return $this->belongsTo(VehicleUsage::class, 'vv_vu_id', 'vu_id');
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('vehicle_manual_violations', 'vv')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vv.vv_ve_id')
            ->leftJoin('vehicle_usages as vu', 'vu.vu_id', '=', 'vv.vv_vu_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vu.vu_sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->select('vv.*', 've.ve_plate_no')
            ->addSelect(
                DB::raw(VvStatus::toCaseSQL()),
                DB::raw(VvStatus::toColorSQL()),
                DB::raw("to_char(vv_violation_datetime, 'YYYY-MM-DD HH24:MI') as vv_violation_datetime_"),
            )
        ;
    }

    public static function indexStatValue($list): bool
    {
        return count($list) > 0;
    }

    public static function importColumns(): array
    {
        return [
            'vv_plate_no'           => [Vehicle::class, 've_plate_no'],
            'vv_violation_datetime' => [VehicleManualViolation::class, 'vv_violation_datetime'],
            'vv_location'           => [VehicleManualViolation::class, 'vv_location'],
            'vv_violation_content'  => [VehicleManualViolation::class, 'vv_violation_content'],
            'vv_fine_amount'        => [VehicleManualViolation::class, 'vv_fine_amount'],
            'vv_penalty_points'     => [VehicleManualViolation::class, 'vv_penalty_points'],
            'vv_status'             => [VehicleManualViolation::class, 'vv_status'],
            'vv_remark'             => [VehicleManualViolation::class, 'vv_remark'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['vv_ve_id']  = Vehicle::plateNoKv($item['vv_plate_no'] ?? null);
            $item['vv_status'] = VvStatus::searchValue($item['vv_status'] ?? null);
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'vv_ve_id'              => ['required', 'integer'],
            'vv_violation_datetime' => ['required', 'date'],
            'vv_violation_content'  => ['nullable', 'string', 'max:200'],
            'vv_location'           => ['nullable', 'string', 'max:255'],
            'vv_fine_amount'        => ['nullable', 'numeric'],
            'vv_penalty_points'     => ['nullable', 'integer'],
            'vv_status'             => ['required', 'integer', Rule::in(VvStatus::label_keys())],
            'vv_remark'             => ['nullable', 'string'],
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

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function vvStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('vv_status')?->label
        );
    }

    protected function vvVehicleUsagesLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => null === $this->getOriginal('vv_vu_id') ? '未匹配' : '已匹配'
        );
    }
}
