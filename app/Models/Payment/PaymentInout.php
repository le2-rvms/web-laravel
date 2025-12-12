<?php

namespace App\Models\Payment;

use App\Attributes\ClassName;
use App\Enum\Payment\IoIoType;
use App\Enum\Payment\RpPayStatus;
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
 * @property int            $io_id           流水序号
 * @property mixed          $io_type         流水类型
 * @property int            $cu_id           客户序号
 * @property int            $pa_id           收付款账号序号
 * @property Carbon         $occur_datetime  发生时间
 * @property float          $occur_amount    发生金额
 * @property float          $account_balance 收款账户当前余额
 * @property null|int       $rp_id           租车收支序号
 * @property Customer       $Customer
 * @property PaymentAccount $PaymentAccount
 * @property Payment        $Payment
 */
class PaymentInout extends Model
{
    use ModelTrait;

    protected $primaryKey = 'io_id';

    protected $guarded = ['io_id'];

    protected $attributes = [];

    protected $casts = [
        'occur_datetime' => 'datetime:Y-m-d H:i:s',
        'io_type'        => IoIoType::class,
    ];

    protected $appends = [
        'io_type_label',
    ];

    public function Customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'cu_id', 'cu_id');
    }

    public function PaymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'pa_id', 'pa_id');
    }

    public function Payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'rp_id', 'rp_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        return DB::query()
            ->from('payment_inouts', 'io')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'io.cu_id')
            ->leftJoin('payment_accounts as pa', 'pa.pa_id', '=', 'io.pa_id')
            ->leftJoin('payments as rp', 'rp.rp_id', '=', 'io.rp_id')
            ->leftJoin('payment_types as pt', 'pt.pt_id', '=', 'rp.pt_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'rp.sc_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->orderByDesc('io.io_id')
            ->select('pa.pa_name', 'pt.pt_name', 'io.occur_amount', 'io.account_balance', 'rp.should_pay_date', 'rp.should_pay_amount', 'cu.contact_name', 'sc.contract_number', 've.plate_no', 'rp.rp_remark')
            ->addSelect(
                DB::raw(IoIoType::toCaseSQL()),
                DB::raw(RpPayStatus::toCaseSQL()),
                DB::raw(RpPayStatus::toColorSQL()),
                DB::raw(" CONCAT(COALESCE(vm.brand_name,'未知品牌'),'-',COALESCE(vm.model_name,'未知车型')) AS brand_full_name"),
                DB::raw("to_char(io.occur_datetime, 'YYYY-MM-DD HH24:MI:SS') as occur_datetime_"),
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'Inout.pa_name'                => fn ($item) => $item->pa_name,
            'Inout.io_type'                => fn ($item) => $item->io_type_label,
            'Customer.contact_name'        => fn ($item) => $item->_contact_name,
            'PaymentType.pt_name'          => fn ($item) => $item->_pt_name,
            'Inout.occur_datetime'         => fn ($item) => $item->occur_datetime_,
            'Inout.occur_amount'           => fn ($item) => $item->occur_amount,
            'Inout.account_balance'        => fn ($item) => $item->account_balance,
            'Payment.should_pay_date'      => fn ($item) => $item->_should_pay_date,
            'Payment.should_pay_amount'    => fn ($item) => $item->_should_pay_amount,
            'SaleContract.contract_number' => fn ($item) => $item->_contract_number,
            'Vehicle.plate_no'             => fn ($item) => $item->_plate_no,
            'VehicleModel.brand_full_name' => fn ($item) => $item->_brand_full_name,
            'Payment.rp_remark'            => fn ($item) => $item->rp_remark,
        ];
    }

    public static function option(Collection $Payments): array
    {
        return [
            preg_replace('/^.*\\\/', '', get_called_class()).'Options' => (function () use ($Payments) {
                $value = [];
                foreach ($Payments as $key => $rp) {
                    $value[] = ['text' => $rp->rp_remark, 'value' => $key];
                }

                return $value;
            })(),
        ];
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function ioTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('io_type')?->label,
        );
    }
}
