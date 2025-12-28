<?php

namespace App\Models\Payment;

use App\Attributes\ClassName;
use App\Enum\Payment\IoType;
use App\Enum\Payment\PPayStatus;
use App\Models\_\ModelTrait;
use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[ClassName('账户流水')]
/**
 * @property int            $io_id              流水序号
 * @property mixed          $io_type            流水类型
 * @property int            $io_cu_id           客户序号
 * @property int            $io_pa_id           收付款账号序号
 * @property Carbon         $io_occur_datetime  发生时间
 * @property float          $io_occur_amount    发生金额
 * @property float          $io_account_balance 收款账户当前余额
 * @property null|int       $io_p_id            租车收支序号
 * @property Customer       $Customer
 * @property PaymentAccount $PaymentAccount
 * @property Payment        $Payment
 */
class PaymentInout extends Model
{
    use ModelTrait;

    // 使用自定义时间戳字段。
    public const CREATED_AT = 'io_created_at';
    public const UPDATED_AT = 'io_updated_at';
    public const UPDATED_BY = 'io_updated_by';

    protected $primaryKey = 'io_id';

    protected $guarded = ['io_id'];

    protected $attributes = [];

    protected $casts = [
        'io_occur_datetime' => 'datetime:Y-m-d H:i:s',
        'io_type'           => IoType::class,
    ];

    protected $appends = [
        'io_type_label',
    ];

    public function Customer(): BelongsTo
    {
        // 关联客户信息。
        return $this->belongsTo(Customer::class, 'io_cu_id', 'cu_id');
    }

    public function PaymentAccount(): BelongsTo
    {
        // 关联收付款账号。
        return $this->belongsTo(PaymentAccount::class, 'io_pa_id', 'pa_id');
    }

    public function Payment(): BelongsTo
    {
        // 关联收付款计划记录。
        return $this->belongsTo(Payment::class, 'io_p_id', 'p_id');
    }

    public static function indexQuery(): Builder
    {
        return DB::query()
            ->from('payment_inouts', 'io')
            // 组装多表信息，便于列表一次取齐。
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'io.io_cu_id')
            ->leftJoin('payment_accounts as pa', 'pa.pa_id', '=', 'io.io_pa_id')
            ->leftJoin('payments as p', 'p.p_id', '=', 'io.io_p_id')
            ->leftJoin('payment_types as pt', 'pt.pt_id', '=', 'p.p_pt_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'p.p_sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.sc_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->orderByDesc('io.io_id')
            ->select('pa.pa_name', 'pt.pt_name', 'io.io_occur_amount', 'io.io_account_balance', 'p.p_should_pay_date', 'p.p_should_pay_amount', 'cu.cu_contact_name', 'sc.sc_no', 've.ve_plate_no', 'p.p_remark')
            ->addSelect(
                // 附加枚举标签、颜色与展示字段。
                DB::raw(IoType::toCaseSQL()),
                DB::raw(PPayStatus::toCaseSQL()),
                DB::raw(PPayStatus::toColorSQL()),
                DB::raw(" CONCAT(COALESCE(vm.vm_brand_name,'未知品牌'),'-',COALESCE(vm.vm_model_name,'未知车型')) AS vm_brand_full_name"),
                DB::raw("to_char(io.io_occur_datetime, 'YYYY-MM-DD HH24:MI:SS') as io_occur_datetime_"),
            )
        ;
    }

    public static function indexColumns(): array
    {
        // 列表列配置，供 PaginateService 渲染。
        return [
            'Inout.pa_name'                => fn ($item) => $item->pa_name,
            'Inout.io_type'                => fn ($item) => $item->io_type_label,
            'Customer.cu_contact_name'     => fn ($item) => $item->_contact_name,
            'PaymentType.pt_name'          => fn ($item) => $item->_pt_name,
            'Inout.occur_datetime'         => fn ($item) => $item->occur_datetime_,
            'Inout.occur_amount'           => fn ($item) => $item->occur_amount,
            'Inout.account_balance'        => fn ($item) => $item->account_balance,
            'Payment.p_should_pay_date'    => fn ($item) => $item->_should_pay_date,
            'Payment.p_should_pay_amount'  => fn ($item) => $item->_should_pay_amount,
            'SaleContract.sc_no'           => fn ($item) => $item->_sc_no,
            'Vehicle.plate_no'             => fn ($item) => $item->_plate_no,
            'VehicleModel.brand_full_name' => fn ($item) => $item->_brand_full_name,
            'Payment.p_remark'             => fn ($item) => $item->p_remark,
        ];
    }

    public static function option(Collection $Payments): array
    {
        $key = static::getOptionKey($key);

        return [
            $key => (function () use ($Payments) {
                $value = [];
                foreach ($Payments as $key => $p) {
                    $value[] = ['text' => $p->p_remark, 'value' => $key];
                }

                return $value;
            })(),
        ];
    }

    public static function options(?\Closure $where = null, ?string $key = null): array
    {
        return [];
    }

    protected function ioTypeLabel(): Attribute
    {
        return Attribute::make(
            // 提供枚举中文标签。
            get: fn () => $this->getAttribute('io_type')?->label,
        );
    }
}
