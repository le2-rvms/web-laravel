<?php

namespace App\Models\Payment;

use App\Attributes\ClassName;
use App\Enum\Payment\PaStatus;
use App\Models\_\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

#[ClassName('收付款账户')]
/**
 * @property int         $pa_id                 账户序号
 * @property string      $pa_name               账户名称
 * @property int         $pa_status             账户状态
 * @property float       $pa_balance            当前余额
 * @property int         $pa_is_weiqifu         是否为微企付收款账号;（1：是，0：否） -- 注：一家公司只能绑定一个微企付收款账号
 * @property int         $pa_is_wechat_pay      是否为微信支付收款账号;（1：是，0：否） -- 注：一家公司只能绑定一个微信支付收款账号
 * @property int         $pa_is_bank_withhold   是否为银行卡代扣收款账号;（1：是，0：否） -- 注：一家公司只能绑定一个银行卡代扣收款账号
 * @property int         $pa_is_alipay_withhold 是否为支付宝代扣收款账号;（1：是，0：否） -- 注：一家公司只能绑定一个支付宝代扣收款账号
 * @property int         $pa_is_alipay_plan     是否为支付宝计划扣款账号;（1：是，0：否）
 * @property null|string $pa_remark             账户备注
 */
class PaymentAccount extends Model
{
    use ModelTrait;

    public const CREATED_AT = 'pa_created_at';
    public const UPDATED_AT = 'pa_updated_at';
    public const UPDATED_BY = 'pa_updated_by';

    protected $primaryKey = 'pa_id';

    protected $guarded = ['pa_id'];

    protected $casts = [
        'pa_status'  => PaStatus::class,
        'pa_balance' => 'decimal:2',
    ];

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('payment_accounts', 'pa')
            ->select('*')
            ->addSelect(
                DB::raw(PaStatus::toCaseSQL()),
                DB::raw(PaStatus::toColorSQL()),
            )
        ;
    }

    public static function options(?\Closure $where = null): array
    {
        $key   = preg_replace('/^.*\\\/', '', get_called_class()).'Options';
        $value = static::query()->toBase()
            ->where('pa_status', '!=', PaStatus::DISABLED)
            ->select(DB::raw('pa_name as text,pa_id as value'))->get()
        ;

        return [$key => $value];
    }
}
