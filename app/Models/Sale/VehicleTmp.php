<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Sale\VtChangeStatus;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType_ShortOnlyShort;
use App\Enum\SaleContract\ScStatus;
use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('临时派车')]
/**
 * @property int                   $vt_id                  临时派车记录序号
 * @property int                   $vt_sc_id               租车合同序号
 * @property int                   $vt_current_ve_id       车辆序号
 * @property int                   $vt_new_ve_id           新车车辆序号
 * @property null|Carbon           $vt_change_start_date   临时派车开始日期
 * @property null|Carbon           $vt_change_end_date     临时派车结束日期
 * @property string|VtChangeStatus $vt_change_status       临时派车状态
 * @property null|mixed            $vt_additional_photos   附加照片；存储照片路径的 JSON 数组
 * @property null|string           $vt_remark              临时派车备注
 * @property SaleContract          $SaleContract
 * @property Vehicle               $CurrentVehicle
 * @property Vehicle               $NewVehicle
 * @property string                $vt_current_ve_plate_no 旧车车牌号
 * @property string                $vt_new_ve_plate_no     新车车牌号
 */
class VehicleTmp extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'vt_created_at';
    public const UPDATED_AT = 'vt_updated_at';
    public const UPDATED_BY = 'vt_updated_by';

    protected $primaryKey = 'vt_id';

    protected $guarded = ['vt_id'];

    protected $attributes = [];

    protected $appends = [
    ];

    protected $casts = [
        'vt_change_status'     => VtChangeStatus::class,
        'vt_change_start_date' => 'datetime:Y-m-d',
    ];

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'vt_sc_id', 'sc_id');
    }

    public function CurrentVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vt_current_ve_id', 've_id');
    }

    public function NewVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vt_new_ve_id', 've_id');
    }

    public static function indexQuery(): Builder
    {
        //        $sc_id = $search['sc_id'] ?? null;
        //        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('vehicle_tmps', 'vt')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vt.vt_sc_id')
            ->leftJoin('vehicles as ve1', 've1.ve_id', '=', 'vt.vt_current_ve_id')
            ->leftJoin('vehicles as ve2', 've2.ve_id', '=', 'vt.vt_new_ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
//            ->when($sc_id, function (Builder $query) use ($sc_id) {
//                $query->where('sc.sc_id', '=', $sc_id);
//            })
//            ->when($cu_id, function (Builder $query) use ($cu_id) {
//                $query->where('sc.sc_cu_id', '=', $cu_id);
//            })
//            ->when(
//                null === $sc_id && null === $cu_id,
//                function (Builder $query) {
//                    $query->orderByDesc('vt.vt_id');
//                },
//                function (Builder $query) {
//                    $query->orderBy('vt.vt_id');
//                }
//            )
            ->select('vt.*', 'sc.*', 'cu.*', 've1.ve_plate_no as current_ve_plate_no', 've2.ve_plate_no as new_ve_plate_no')
            ->addSelect(
                DB::raw(VtChangeStatus::toCaseSQL()),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Customer.cu_contact_name'               => fn ($item) => $item->cu_contact_name,
            'Customer.contact_phone'                 => fn ($item) => $item->contact_phone,
            'VehicleReplacement.current_ve_plate_no' => fn ($item) => $item->current_ve_plate_no,
            'VehicleReplacement.new_ve_plate_no'     => fn ($item) => $item->new_ve_plate_no,
            'VehicleReplacement.change_start_date'   => fn ($item) => $item->change_start_date,
            'VehicleReplacement.change_end_date'     => fn ($item) => $item->change_end_date,
            'VehicleReplacement.change_status'       => fn ($item) => $item->change_status_label,
            'VehicleReplacement.vt_remark'           => fn ($item) => $item->vt_remark,
        ];
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $value = static::options_value($where);

        return [$key => $value];
    }

    public static function options_value(?\Closure $where = null): array
    {
        return DB::query()
            ->from('vehicle_tmps', 'vt')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vt.vt_sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vt.vt_new_ve_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->where($where)
            ->orderBy('vt.vt_id', 'desc')
            ->select(
                DB::raw(sprintf(
                    "CONCAT(cu.cu_contact_name,'|',%s,'|', ve.ve_plate_no ,'|',  %s, %s ,'|', %s ) as text,vt.vt_id as value",
                    "(CONCAT(SUBSTRING(cu.cu_contact_phone, 1, 0), '', SUBSTRING(cu.cu_contact_phone, 8, 4)) )",
                    ScPaymentPeriod::toCaseSQL(false),
                    ScRentalType_ShortOnlyShort::toCaseSQL(false),
                    ScStatus::toCaseSQL(false)
                ))
            )
            ->get()->toArray()
        ;
    }

    protected function vtAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
