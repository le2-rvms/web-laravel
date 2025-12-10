<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType_ShortOnlyShort;
use App\Enum\Sale\VrReplacementStatus;
use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('换车')]
/**
 * @property int                        $vr_id                  换车记录序号
 * @property int                        $so_id                  租车合同序号
 * @property int                        $current_ve_id          需换车车辆序号
 * @property int                        $new_ve_id              新车车辆序号
 * @property null|Carbon                $replacement_date       换车日期
 * @property null|Carbon                $replacement_start_date 换车开始日期
 * @property null|Carbon                $replacement_end_date   换车结束日期
 * @property string|VrReplacementStatus $replacement_status     换车状态
 * @property null|mixed                 $additional_photos      附加照片；存储照片路径的 JSON 数组
 * @property null|string                $vr_remark              换车备注
 * @property SaleOrder                  $SaleOrder
 * @property Vehicle                    $CurrentVehicle
 * @property Vehicle                    $NewVehicle
 * @property string                     $current_ve_plate_no    旧车车牌号
 * @property string                     $new_ve_plate_no        新车车牌号
 */
class VehicleReplacement extends Model
{
    use ModelTrait;

    protected $primaryKey = 'vr_id';

    protected $guarded = ['vr_id'];

    protected $attributes = [];

    protected $appends = [
    ];

    protected $casts = [
        'replacement_status'     => VrReplacementStatus::class,
        'replacement_start_date' => 'datetime:Y-m-d',
    ];

    public function SaleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'so_id', 'so_id');
    }

    public function CurrentVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'current_ve_id', 've_id');
    }

    public function NewVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'new_ve_id', 've_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $so_id = $search['so_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('vehicle_replacements', 'vr')
            ->leftJoin('sale_orders as so', 'so.so_id', '=', 'vr.so_id')
            ->leftJoin('vehicles as ve1', 've1.ve_id', '=', 'vr.current_ve_id')
            ->leftJoin('vehicles as ve2', 've2.ve_id', '=', 'vr.new_ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->when($so_id, function (Builder $query) use ($so_id) {
                $query->where('so.so_id', '=', $so_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('so.cu_id', '=', $cu_id);
            })
            ->when(
                null === $so_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('vr.vr_id');
                },
                function (Builder $query) {
                    $query->orderBy('vr.vr_id');
                }
            )
            ->select('vr.*', 'so.*', 'cu.*', 've1.plate_no as current_ve_plate_no', 've2.plate_no as new_ve_plate_no')
            ->addSelect(
                DB::raw(VrReplacementStatus::toCaseSQL()),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Customer.contact_name'                     => fn ($item) => $item->contact_name,
            'Customer.contact_phone'                    => fn ($item) => $item->contact_phone,
            'VehicleReplacement.current_ve_plate_no'    => fn ($item) => $item->current_ve_plate_no,
            'VehicleReplacement.new_ve_plate_no'        => fn ($item) => $item->new_ve_plate_no,
            'VehicleReplacement.replacement_date'       => fn ($item) => $item->replacement_date,
            'VehicleReplacement.replacement_start_date' => fn ($item) => $item->replacement_start_date,
            'VehicleReplacement.replacement_end_date'   => fn ($item) => $item->replacement_end_date,
            'VehicleReplacement.replacement_status'     => fn ($item) => $item->replacement_status_label,
            'VehicleReplacement.vr_remark'              => fn ($item) => $item->vr_remark,
        ];
    }

    public static function options(?\Closure $where = null): array
    {
        $key = preg_replace('/^.*\\\/', '', get_called_class()).'Options';

        $value = static::options_value($where);

        return [$key => $value];
    }

    public static function options_value(?\Closure $where = null): array
    {
        return DB::query()
            ->from('vehicle_replacements', 'vr')
            ->leftJoin('sale_orders as so', 'so.so_id', '=', 'vr.so_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vr.new_ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'so.cu_id')
            ->where($where)
            ->orderBy('vr.vr_id', 'desc')
            ->select(
                DB::raw(sprintf(
                    "CONCAT(cu.contact_name,'|',%s,'|', ve.plate_no ,'|',  %s, %s ,'|', %s ) as text,vr.vr_id as value",
                    "(CONCAT(SUBSTRING(cu.contact_phone, 1, 0), '', SUBSTRING(cu.contact_phone, 8, 4)) )",
                    SoPaymentDayType::toCaseSQL(false),
                    SoRentalType_ShortOnlyShort::toCaseSQL(false),
                    SoOrderStatus::toCaseSQL(false)
                ))
            )
            ->get()->toArray()
        ;
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
