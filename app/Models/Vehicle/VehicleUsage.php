<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
use App\Models\_\ModelTrait;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[ClassName('行程')]
/**
 * @property int          $vu_id        行程记录序号
 * @property int          $sc_id        租车合同序号
 * @property int          $ve_id        车辆序号
 * @property int          $start_vi_id  行程开始的验车序号
 * @property null|int     $end_vi_id    行程结束的验车序号
 * @property null|string  $vu_remark    行程备注
 * @property SaleContract $SaleContract
 */
class VehicleUsage extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'vu_created_at';
    public const UPDATED_AT = 'vu_updated_at';
    public const UPDATED_BY = 'vu_updated_by';

    protected $primaryKey = 'vu_id';

    protected $guarded = ['vu_id'];

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'vu_sc_id', 'sc_id');
    }

    // 定义与原始车辆的关系
    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vu_ve_id', 've_id');
    }

    public function VehicleInspectionStart(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'vu_start_vi_id', 'vi_id');
    }

    public function VehicleInspectionEnd(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'vu_end_vi_id', 'vi_id');
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('vehicle_usages', 'vu')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vu.vu_sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vu.vu_ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->leftJoin('vehicle_inspections as vi1', 'vi1.vi_id', '=', 'vu.vu_start_vi_id')
            ->leftJoin('vehicle_inspections as vi2', 'vi2.vi_id', '=', 'vu.vu_end_vi_id')
            ->select('vu.*', 'sc.*', 've.*', 'cu.*')
            ->addSelect(
                DB::raw(ScRentalType::toCaseSQL()),
                DB::raw(ScPaymentPeriod::toCaseSQL()),
                DB::raw(ScStatus::toCaseSQL()),
                // 行程间隔按开始验车时间减结束验车时间计算，正负取决于时间顺序。
                DB::raw('EXTRACT(EPOCH FROM vi1.vi_inspection_datetime - vi2.vi_inspection_datetime) / 86400.0 as vu_vi_interval_day'),
                DB::raw("to_char(vi1.vi_inspection_datetime, 'YYYY-MM-DD HH24:MI:SS') as vi_start_inspection_datetime_"),
                DB::raw("to_char(vi2.vi_inspection_datetime, 'YYYY-MM-DD HH24:MI:SS') as vi_end_inspection_datetime_"),
            )
        ;
    }

    public static function optionsQuery(): Builder
    {
        return static::query();
    }
}
