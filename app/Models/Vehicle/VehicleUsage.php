<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\ScScStatus;
use App\Models\_\ModelTrait;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
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

    protected $primaryKey = 'vu_id';

    protected $guarded = ['vu_id'];

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'sc_id', 'sc_id');
    }

    // 定义与原始车辆的关系
    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 've_id', 've_id');
    }

    public function VehicleInspectionStart(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'start_vi_id', 'vi_id');
    }

    public function VehicleInspectionEnd(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'end_vi_id', 'vi_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $sc_id = $search['sc_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('vehicle_usages', 'vu')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vu.sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vu.ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->leftJoin('vehicle_inspections as vi1', 'vi1.vi_id', '=', 'vu.start_vi_id')
            ->leftJoin('vehicle_inspections as vi2', 'vi2.vi_id', '=', 'vu.end_vi_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vu.ve_id', '=', $ve_id);
            })
            ->when($sc_id, function (Builder $query) use ($sc_id) {
                $query->where('vu.sc_id', '=', $sc_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('sc.cu_id', '=', $cu_id);
            })
            ->when(
                null === $ve_id && null === $sc_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vu.vu_id');
                },
                function (Builder $query) {
                    $query->orderBy('vu.vu_id');
                }
            )
            ->select('vu.*', 'sc.*', 've.*', 'cu.*')
            ->addSelect(
                DB::raw(ScRentalType::toCaseSQL()),
                DB::raw(ScPaymentDayType::toCaseSQL()),
                DB::raw(ScScStatus::toCaseSQL()),
                DB::raw('EXTRACT(EPOCH FROM vi1.inspection_datetime - vi2.inspection_datetime) / 86400.0 as vu_interval_day'),
                DB::raw("to_char(vi1.inspection_datetime, 'YYYY-MM-DD HH24:MI:SS') as start_inspection_datetime_"),
                DB::raw("to_char(vi2.inspection_datetime, 'YYYY-MM-DD HH24:MI:SS') as end_inspection_datetime_"),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }
}
