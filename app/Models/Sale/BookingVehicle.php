<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Booking\BvIsListed;
use App\Enum\Booking\BvType;
use App\Models\_\ModelTrait;
use App\Models\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('预定车辆')]
/**
 * @property int             $bv_id                 预订车辆序号
 * @property BvType|string   $bv_type               租期类型
 * @property string          $bv_plate_no           车牌号
 * @property null|Carbon     $bv_pickup_date        提车日期
 * @property int             $bv_rent_per_amount    每期租金;(元)
 * @property int             $bv_deposit_amount     押金;(元)
 * @property int             $bv_min_rental_periods 最短租期;(天、周、月)
 * @property null|Carbon     $bv_registration_date  注册日期
 * @property int             $bv_mileage            行驶里程;(公里)
 * @property int             $bv_service_interval   保养周期;(公里)
 * @property null|array      $bv_props              车辆信息
 * @property string          $bv_note               备注信息
 * @property null|array      $bv_photo              车辆照片；存储照片路径的 JSON 数组
 * @property null|array      $bv_additional_photos  附加照片；存储照片路径的 JSON 数组
 * @property null|BvIsListed $bv_is_listed          上架状态;
 * @property Carbon          $bv_listed_at          上架时间
 *                                                  --
 * @property Vehicle         $Vehicle
 */
class BookingVehicle extends Model
{
    use ModelTrait;

    // 使用自定义时间戳字段。
    public const CREATED_AT = 'bv_created_at';
    public const UPDATED_AT = 'bv_updated_at';
    public const UPDATED_BY = 'bv_updated_by';

    protected $primaryKey = 'bv_id';

    protected $guarded = ['bv_id'];

    protected $casts = [
        // 枚举字段统一 cast 为类枚举。
        'bv_props'     => 'array',
        'bv_type'      => BvType::class,
        'bv_is_listed' => BvIsListed::class,
    ];

    protected $appends = [
        // 追加枚举中文标签。
        'bv_type_label',
        'bv_is_listed_label',
    ];

    protected $attributes = [];

    public function Vehicle(): BelongsTo
    {
        // 通过车牌号关联车辆信息。
        return $this->belongsTo(Vehicle::class, 'bv_plate_no', 've_plate_no')->withDefault()->with('VehicleModel');
    }

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->from('booking_vehicles', 'bv')
            // 拼装车辆信息用于列表展示。
            ->leftJoin('vehicles as ve', 'bv.bv_plate_no', '=', 've.ve_plate_no')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->select('bv.*', 've.*', 'vm.*')
            ->addSelect(
                // 附加枚举 label 与上架时长。
                DB::raw(BvType::toCaseSQL()),
                DB::raw('(NOW()::date - bv_listed_at::date) AS listed_days_diff'),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        $key = static::getOptionKey($key);

        $value = DB::query()
            ->from('booking_vehicles', 'bv')
            ->leftJoin('vehicles as ve', 'bv.bv_plate_no', '=', 've.ve_plate_no')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            // 下拉仅展示已上架的预定车辆。
            ->where('bv.bv_is_listed', '=', BvIsListed::LISTED)
            ->select(DB::raw("CONCAT(ve.ve_plate_no,'-',COALESCE(vm.vm_brand_name,'未知品牌'),'-', COALESCE(vm.vm_model_name,'未知车型')) as text,bv.bv_id as value"))
            ->get()
        ;

        return [$key => $value];
    }

    protected function bvPhoto(): Attribute
    {
        // 统一走上传文件访问器。
        return $this->uploadFile();
    }

    protected function bvAdditionalPhotos(): Attribute
    {
        // 统一走上传文件数组访问器。
        return $this->uploadFileArray();
    }

    protected function bvTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bv_type')?->label
        );
    }

    protected function bvIsListedLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bv_is_listed')?->label
        );
    }

    protected function boNo(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // 缺省生成预定编号。
                if (!$value) {
                    return 'BK'.gen_sc_no();
                }
            }
        );
    }
}
