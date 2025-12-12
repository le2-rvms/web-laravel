<?php

namespace App\Models\Vehicle;

use App\Attributes\ClassName;
use App\Enum\Vehicle\VvPaymentStatus;
use App\Enum\Vehicle\VvProcessStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('违章', '记录')]
/**
 * @property int                 $vv_id                违章记录序号
 * @property string              $decision_number      决定书编号
 * @property null|int            $ve_id                车辆序号；指向车辆表
 * @property string              $plate_no             号牌号码
 * @property null|int            $vu_id                车辆使用时间段记录序号
 * @property Carbon              $violation_datetime   违章发生的日时
 * @property string              $violation_content    违法行为
 * @property string              $location             违章发生地点
 * @property null|float          $fine_amount          违章罚款金额
 * @property null|int            $penalty_points       违章扣分
 * @property int|VvProcessStatus $process_status       违章处理状态
 * @property int|VvPaymentStatus $payment_status       违章交款状态
 * @property string              $process_status_label 违章处理状态
 * @property string              $payment_status_label 违章交款状态
 * @property null|string         $vv_remark            违章备注
 * @property VehicleUsage        $VehicleUsage
 * @property array               $vv_response
 * @property string              $vv_code
 */
class VehicleViolation extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vv_id';

    protected $guarded = ['vv_id'];

    protected $casts = [
        'violation_datetime' => 'datetime:Y-m-d H:i',
        'process_status'     => VvProcessStatus::class,
        'payment_status'     => VvPaymentStatus::class,
    ];

    protected $appends = [
        'process_status_label',
        'payment_status_label',
        'vehicle_usages_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'plate_no', 'plate_no');
    }

    public function VehicleUsage(): BelongsTo
    {
        return $this->BelongsTo(VehicleUsage::class, 'vu_id', 'vu_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $ve_id = $search['ve_id'] ?? null;
        $sc_id = $search['sc_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('vehicle_violations', 'vv')
            ->leftJoin('vehicles as ve', 've.plate_no', '=', 'vv.plate_no')
            ->leftJoin('vehicle_usages as vu', 'vu.vu_id', '=', 'vv.vu_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vu.sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->when($ve_id, function (Builder $query) use ($ve_id) {
                $query->where('vv.ve_id', '=', $ve_id);
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
                    $query->orderByDesc('vv.vv_id');
                },
                function (Builder $query) {
                    $query->orderBy('vv.vv_id');
                }
            )
            ->select(
                'vv.*',
                DB::raw("to_char(violation_datetime, 'YYYY-MM-DD HH24:MI') as violation_datetime_"),
                DB::raw(VvPaymentStatus::toCaseSQL()),
                DB::raw(VvProcessStatus::toCaseSQL()),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'VehicleViolation.plate_no'           => fn ($item) => $item->plate_no,
            'VehicleViolation.violation_datetime' => fn ($item) => $item->violation_datetime_,
            'VehicleViolation.location'           => fn ($item) => $item->location,
            'VehicleViolation.fine_amount'        => fn ($item) => $item->fine_amount,
            'VehicleViolation.penalty_points'     => fn ($item) => $item->penalty_points,
            'VehicleViolation.payment_status'     => fn ($item) => $item->payment_status_label,
            'VehicleViolation.process_status'     => fn ($item) => $item->process_status_label,
            'VehicleViolation.violation_content'  => fn ($item) => $item->violation_content,
            'VehicleViolation.vv_remark'          => fn ($item) => $item->vv_remark,
        ];
    }

    public static function indexStat($list): bool
    {
        return count($list) > 0;
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function processStatusLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('process_status')?->label
        );
    }

    protected function paymentStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('payment_status')?->label
        );
    }

    protected function vehicleUsagesLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => null === $this->getOriginal('vu_id') ? '未匹配' : '已匹配'
        );
    }
}
