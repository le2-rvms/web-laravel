<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\ScRentalType_Short;
use App\Enum\Sale\SctPaymentDayType;
use App\Enum\Sale\SctRentalType;
use App\Enum\Sale\SctSctStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('租车合同模板')]
/**
 * @property int         $sct_id                          签约模板序号
 * @property string      $sct_name                        签约模板名称
 * @property mixed       $sct_status                      签约模板状态
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
 * @property null|string $sc_remark                       合同备注
 */
class SaleContractTpl extends Model
{
    use ModelTrait;

    protected $primaryKey = 'sct_id';

    protected $guarded = ['sct_id'];

    protected $casts = [
        'sct_status'       => SctSctStatus::class,
        'rental_type'      => ScRentalType::class,
        'payment_day_type' => ScPaymentDayType::class,
    ];

    protected $attributes = [
        'sct_status' => SctSctStatus::ENABLED,
    ];

    protected $appends = [
        'sct_status_label',
        'rental_type_label',
        'rental_type_short_label',
        'payment_day_type_label',
        'payment_day_label',
    ];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('sale_contract_tpls', 'sct')
            ->orderByDesc('sct.sct_id')
            ->select('sct.*')
            ->addSelect(
                DB::raw(SctRentalType::toCaseSQL()),
                DB::raw(SctPaymentDayType::toCaseSQL()),
                DB::raw(SctSctStatus::toCaseSQL()),
                DB::raw(SctSctStatus::toColorSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = DB::query()
            ->from('sale_contract_tpls', 'sct')
            ->where('sct.sct_status', '=', SctSctStatus::ENABLED)
            ->orderBy('sct.sct_id', 'desc')
            ->select(DB::raw('sct_name as text,sct.sct_id as value'))
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

                $class = ScPaymentDayType::payment_day_classes[$payment_day_type->value];

                $map = $class::LABELS;

                return $map[$this->getOriginal('payment_day')] ?? '';
            }
        );
    }

    protected function sctStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('sct_status')?->label ?? null
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
            get : fn ($value) => ScRentalType_Short::tryFrom($this->getRawOriginal('rental_type'))?->label,
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
