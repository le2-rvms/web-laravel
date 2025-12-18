<?php

namespace App\Models\Sale;

use App\Attributes\ClassName;
use App\Enum\SaleContract\ScRentalType_Short;
use App\Enum\SaleContract\SctPaymentPeriod;
use App\Enum\SaleContract\SctRentalType;
use App\Enum\SaleContract\SctStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('租车合同模板')]
/**
 * @property int         $sct_id                              签约模板序号
 * @property string      $sct_name                            签约模板名称
 * @property mixed       $sct_status                          签约模板状态
 * @property mixed       $sct_rental_type                     租车类型；长租或短租
 * @property mixed       $sct_payment_period                  付款类型；例如月付预付、月付后付等
 * @property string      $sct_no_prefix                       合同编号前缀
 * @property int         $sct_free_days                       免租天数
 * @property null|int    $sct_installments                    分期数
 * @property null|float  $sct_deposit_amount                  一次性押金金额
 * @property null|float  $sct_management_fee_amount           一次性管理费金额;
 * @property null|float  $sct_rent_amount                     每期租金金额;短租的每日金额
 * @property null|float  $sct_insurance_base_fee_amount       基础保险费金额
 * @property null|float  $sct_insurance_additional_fee_amount 附加保险费总金额
 * @property null|float  $sct_other_fee_amount                其他费总金额
 * @property null|int    $sct_payment_day                     付款日
 * @property null|array  $sct_additional_photos               附加照片
 * @property null|array  $sct_additional_file                 附加文件
 * @property null|string $sct_cus_1                           自定义合同内容1
 * @property null|string $sct_cus_2                           自定义合同内容2
 * @property null|string $sct_cus_3                           自定义合同内容3
 * @property null|string $sct_discount_plan                   优惠方案
 * @property null|string $sct_remark                          合同备注
 */
class SaleContractTpl extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'sct_created_at';
    public const UPDATED_AT = 'sct_updated_at';
    public const UPDATED_BY = 'sct_updated_by';

    protected $primaryKey = 'sct_id';

    protected $guarded = ['sct_id'];

    protected $casts = [
        'sct_status'         => SctStatus::class,
        'sct_rental_type'    => SctRentalType::class,
        'sct_payment_period' => SctPaymentPeriod::class,
    ];

    protected $attributes = [
        'sct_status' => SctStatus::ENABLED,
    ];

    protected $appends = [
        'sct_status_label',
        'sct_rental_type_label',
        'sct_rental_type_short_label',
        'sct_payment_period_label',
        'sct_payment_day_label',
    ];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('sale_contract_tpls', 'sct')
            ->orderByDesc('sct.sct_id')
            ->select('sct.*')
            ->addSelect(
                DB::raw(SctRentalType::toCaseSQL()),
                DB::raw(SctPaymentPeriod::toCaseSQL()),
                DB::raw(SctStatus::toCaseSQL()),
                DB::raw(SctStatus::toColorSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = DB::query()
            ->from('sale_contract_tpls', 'sct')
            ->where('sct.sct_status', '=', SctStatus::ENABLED)
            ->orderBy('sct.sct_id', 'desc')
            ->select(DB::raw('sct_name as text,sct.sct_id as value'))
            ->get()->toArray()
        ;

        return [$key => $value];
    }

    protected function sctPaymentPeriodLabel(): Attribute
    {
        return Attribute::make(
            get : fn () => $this->getAttribute('sct_payment_period')?->label
        );
    }

    protected function sctPaymentDayLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $sct_payment_period = $this->getAttribute('sct_payment_period');
                if (null === $sct_payment_period) {
                    return null;
                }

                $class = SctPaymentPeriod::payment_day_classes[$sct_payment_period->value];

                $map = $class::LABELS;

                return $map[$this->getOriginal('sct_payment_day')] ?? '';
            }
        );
    }

    protected function sctStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('sct_status')?->label ?? null
        );
    }

    protected function sctRentalTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->getAttribute('sct_rental_type')?->label
        );
    }

    protected function sctRentalTypeShortLabel(): Attribute
    {
        return Attribute::make(
            get : fn ($value) => ScRentalType_Short::tryFrom($this->getRawOriginal('sct_rental_type'))?->label,
        );
    }

    protected function scNo(): Attribute
    {
        return Attribute::make(
            get: function () {
                $datetime = \DateTime::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
                $datetime->setTimezone(new \DateTimeZone(date_default_timezone_get()));  // 转换为目标时区
                $no = $datetime->format('ymdHisv');

                return $this->getOriginal('sct_no_prefix').$no;
            }
        );
    }

    protected function sctAdditionalFile(): Attribute
    {
        return $this->uploadFile();
    }

    protected function sctAdditionalPhotos(): Attribute
    {
        return $this->uploadFileArray();
    }
}
