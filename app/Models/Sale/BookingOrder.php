<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Booking\BoOrderStatus;
use App\Enum\Booking\BoPaymentStatus;
use App\Enum\Booking\BoRefundStatus;
use App\Enum\Booking\BoSource;
use App\Enum\Booking\BoType;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use App\Models\Vehicle\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('预定租车')]
/**
 * @property int                    $bo_id                   预定租车序号
 * @property string                 $bo_no                   预定租车编号
 * @property BoSource|string        $bo_source               预定租车来源
 * @property int                    $bo_cu_id                客户ID
 * @property string                 $bo_plate_no             车牌号
 * @property BoType|string          $bo_type                 租期类型
 * @property Carbon                 $bo_pickup_date          提车日期
 * @property int                    $bo_rent_per_amount      租金;(元)
 * @property int                    $bo_deposit_amount       押金;(元)
 * @property array                  $bo_props                车辆信息
 * @property Carbon                 $bo_registration_date    注册日期
 * @property int                    $bo_mileage              行驶里程;(公里)
 * @property int                    $bo_service_interval     保养周期;(公里)
 * @property int                    $bo_min_rental_periods   最短租期;(天、周、月)
 * @property BoPaymentStatus|string $bo_payment_status       支付状态
 * @property BoOrderStatus|string   $bo_order_status         预定租车状态
 * @property BoRefundStatus|string  $bo_refund_status        退款状态
 * @property string                 $bo_note                 其他信息
 * @property string                 $bo_earnest_amount       定金;(元)
 * @property Carbon                 $bo_order_at             上架时间
 *                                                           -
 * @property Customer               $Customer
 * @property Vehicle                $Vehicle
 *                                                           -
 * @property string                 $bo_source_label
 * @property string                 $bo_payment_status_label
 * @property string                 $bo_order_status_label
 * @property string                 $bo_refund_status_label
 */
class BookingOrder extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'bo_created_at';
    public const UPDATED_AT = 'bo_updated_at';
    public const UPDATED_BY = 'bo_updated_by';

    protected $primaryKey = 'bo_id';

    protected $guarded = ['bo_id'];

    protected $casts = [
        'bo_props'             => 'array',
        'bo_type'              => BoType::class,
        'bo_source'            => BoSource::class,
        'bo_pickup_date'       => 'datetime:Y-m-d',
        'bo_registration_date' => 'datetime:Y-m-d',
        'bo_payment_status'    => BoPaymentStatus::class,
        'bo_order_status'      => BoOrderStatus::class,
        'bo_refund_status'     => BoRefundStatus::class,
    ];

    protected $appends = [
        'bo_type_label',
        'bo_source_label',
        'bo_payment_status_label',
        'bo_order_status_label',
        'bo_refund_status_label',
    ];

    public function Vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'bo_plate_no', 've_plate_no')->with('VehicleModel');
    }

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'bo_cu_id', 'cu_id')->withDefault();
    }

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->from('booking_orders', 'bo')
            ->leftJoin('vehicles as ve', 'bo.bo_plate_no', '=', 've.ve_plate_no')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'bo.bo_cu_id')
            ->select('bo.*', 've.*', 'vm.*', 'cu.*')
            ->addSelect(
                DB::raw(BoType::toCaseSQL()),
                DB::raw(BoSource::toCaseSQL()),
                DB::raw(BoPaymentStatus::toCaseSQL()),
                DB::raw(BoOrderStatus::toCaseSQL()),
                DB::raw(BoRefundStatus::toCaseSQL()),
                DB::raw("to_char(bo.bo_order_at, 'YYYY-MM-DD HH24:MI:SS') as bo_order_at"),
            )
        ;
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function boTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bo_type')?->label
        );
    }

    protected function boSourceLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bo_source')?->label
        );
    }

    protected function boPaymentStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bo_payment_status')?->label
        );
    }

    protected function boOrderStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bo_order_status')?->label
        );
    }

    protected function boRefundStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('bo_refund_status')?->label
        );
    }
}
