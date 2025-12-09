<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType;
use App\Enum\Sale\SoRentalType_Short;
use App\Enum\Sale\SotPaymentDayType;
use App\Enum\Sale\SotRentalType;
use App\Enum\Sale\SotSotStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('租车合同模板')]
/**
 * @property int         $sot_id                          签约模板序号
 * @property string      $sot_name                        签约模板名称
 * @property mixed       $sot_status                      签约模板状态
 * @property mixed       $rental_type                     租车类型；长租或短租
 * @property mixed       $payment_day_type                付款类型；例如月付预付、月付后付等
 * @property string      $contract_number_prefix          合同编号前缀
 * @property int         $free_days                       免租天数
 * @property null|int    $installments                    分期数
 * @property null|float  $deposit_amount                  一次性押金金额
 * @property null|float  $management_fee_amount           一次性管理费金额;
 * @property null|float  $rent_amount                     每期租金金额;短租的每日金额
 * @property null|float  $insurance_base_fee_amount       基础保险费金额
 * @property null|float  $insurance_additional_fee_amount 附加保险费总金额
 * @property null|float  $other_fee_amount                其他费总金额
 * @property null|int    $payment_day                     付款日
 * @property null|array  $additional_photos               附加照片
 * @property null|array  $additional_file                 附加文件
 * @property null|string $cus_1                           自定义合同内容1
 * @property null|string $cus_2                           自定义合同内容2
 * @property null|string $cus_3                           自定义合同内容3
 * @property null|string $discount_plan                   优惠方案
 * @property null|string $so_remark                       合同备注
 */
class SaleOrderTpl extends Model
{
    use ModelTrait;

    protected $primaryKey = 'sot_id';

    protected $guarded = ['sot_id'];

    protected $casts = [
        'sot_status'       => SotSotStatus::class,
        'rental_type'      => SoRentalType::class,
        'payment_day_type' => SoPaymentDayType::class,
    ];

    protected $attributes = [
        'sot_status' => SotSotStatus::ENABLED,
    ];

    protected $appends = [
        'sot_status_label',
        'rental_type_label',
        'rental_type_short_label',
        'payment_day_type_label',
        'payment_day_label',
    ];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('sale_order_tpls', 'sot')
            ->orderByDesc('sot.sot_id')
            ->select('sot.*')
            ->addSelect(
                DB::raw(SotRentalType::toCaseSQL()),
                DB::raw(SotPaymentDayType::toCaseSQL()),
                DB::raw(SotSotStatus::toCaseSQL()),
                DB::raw(SotSotStatus::toColorSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = DB::query()
            ->from('sale_order_tpls', 'sot')
            ->where('sot.sot_status', '=', SotSotStatus::ENABLED)
            ->orderBy('sot.sot_id', 'desc')
            ->select(DB::raw('sot_name as text,sot.sot_id as value'))
            ->get()->toArray()
        ;

        return [$key => $value];
    }

    protected function paymentDayTypeLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('payment_day_type')?->label
        );
    }

    protected function paymentDayLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $payment_day_type = $this->getAttribute('payment_day_type');
                if (null === $payment_day_type) {
                    return null;
                }

                $class = SoPaymentDayType::payment_day_classes[$payment_day_type->value];

                $map = $class::LABELS;

                return $map[$this->getOriginal('payment_day')] ?? '';
            }
        );
    }

    protected function sotStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('sot_status')?->label ?? null
        );
    }

    protected function rentalTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('rental_type')?->label
        );
    }

    protected function rentalTypeShortLabel(): Attribute
    {
        return Attribute::make(
            get : fn ($value) => SoRentalType_Short::tryFrom($this->getRawOriginal('rental_type'))?->label,
        );
    }

    protected function contractNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                $datetime = \DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
                $datetime->setTimezone(new \DateTimeZone(date_default_timezone_get()));  // 转换为目标时区
                $contract_number = $datetime->format('ymdHisv');

                return $this->getOriginal('contract_number_prefix').$contract_number;
            }
        );
    }

    protected function additionalFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function additionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
