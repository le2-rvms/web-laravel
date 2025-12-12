<?php

namespace App\Models\Payment;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Payment\RpShouldPayDate_DDD;
use App\Enum\Sale\ScScStatus;
use App\Exceptions\ClientException;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleRepair;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('财务', '记录')]
#[ColumnDesc('rp_id', required: true, )]
#[ColumnDesc('sc_id', required: true, )]
#[ColumnDesc('pt_id', required: true, enum_class: RpPtId::class)]
#[ColumnDesc('should_pay_date', required: true, type: ColumnType::DATE)]
#[ColumnDesc('should_pay_amount', required: true, )]
#[ColumnDesc('pay_status', required: true, enum_class: RpPayStatus::class)]
#[ColumnDesc('actual_pay_date', type: ColumnType::DATE)]
#[ColumnDesc('actual_pay_amount')]
#[ColumnDesc('pa_id')]
#[ColumnDesc('rp_remark')]
#[ColumnDesc('is_valid', required: true, enum_class: RpIsValid::class)]
#[ColumnDesc('vr_id')]
#[ColumnDesc('vm_id')]
#[ColumnDesc('vi_id')]
/**
 * @property int                $rp_id             财务记录序号
 * @property int                $sc_id             租车合同序号
 * @property int                $pt_id             付款类型序号；引用payment_types表
 * @property Carbon|string      $should_pay_date   应收/应付日期
 * @property string             $day_of_week_name  应收/应付星期
 * @property float              $should_pay_amount 应收/付金额
 * @property RpPayStatus|string $pay_status        付款状态；例如已支付、未支付、失败等
 * @property null|Carbon        $actual_pay_date   实际收付日期
 * @property null|float         $actual_pay_amount 实际收付金额
 * @property null|int           $pa_id             支付账户序号
 * @property null|string        $rp_remark         财务备注
 * @property int|RpIsValid      $is_valid          是否有效
 * @property null               $vr_id             车辆维修序号
 * @property null               $vm_id             车辆维修序号
 * @property null               $vi_id             车辆检查序号
 * @property array|mixed        $period            ;start_d 、end_d
 * @property SaleContract       $SaleContract
 * @property PaymentAccount     $PaymentAccount
 * @property PaymentType        $PaymentType
 * @property null|string        $pay_status_label  支付状态-中文
 * @property null|string        $is_valid_label    有效状态-中文
 */
class Payment extends Model
{
    use ModelTrait;

    use ImportTrait;

    protected $primaryKey = 'rp_id';

    protected $guarded = ['rp_id'];

    protected $attributes = [
        'pay_status' => RpPayStatus::UNPAID,
        'is_valid'   => RpIsValid::VALID,
    ];

    protected $casts = [
        'pay_status'        => RpPayStatus::class,
        'should_pay_amount' => 'decimal:2',
        'actual_pay_amount' => 'decimal:2',
        'is_valid'          => RpIsValid::class,
        //        'pt_id'             => RpPtId::class, // !!! 因为是外键，所以不能做转换。
    ];

    protected $appends = [
        'day_of_week_name',
        'pay_status_label',
        'is_valid_label',
    ];

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'sc_id', 'sc_id');
    }

    public function PaymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'pt_id', 'pt_id');
    }

    public function PaymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'pa_id', 'pa_id');
    }

    public function VehicleRepair(): BelongsTo
    {
        return $this->belongsTo(VehicleRepair::class, 'vr_id', 'vr_id');
    }

    public function VehicleMaintenance(): BelongsTo
    {
        return $this->belongsTo(VehicleMaintenance::class, 'vm_id', 'vm_id');
    }

    public function VehicleInspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'vi_id', 'vi_id');
    }

    public static function indexQuery(array $search = []): Builder
    {
        $sc_id = $search['sc_id'] ?? null;
        $cu_id = $search['cu_id'] ?? null;

        return DB::query()
            ->from('payments', 'rp')
            ->leftJoin('payment_accounts as pa', 'pa.pa_id', '=', 'rp.pa_id')
            ->leftJoin('payment_types as pt', 'pt.pt_id', '=', 'rp.pt_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'rp.sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.vm_id')
            ->when($sc_id, function (Builder $query) use ($sc_id) {
                $query->where('rp.sc_id', '=', $sc_id);
            })
            ->when($cu_id, function (Builder $query) use ($cu_id) {
                $query->where('sc.cu_id', '=', $cu_id);
            })
            ->when(
                null === $sc_id && null === $cu_id,
                function (Builder $query) {
                    $query->orderByDesc('rp.sc_id')->orderby('rp.rp_id');
                },
                function (Builder $query) {
                    $query->orderBy('rp.sc_id')->orderby('rp.should_pay_date')->orderby('rp.rp_id');
                }
            )
            ->select('*')
            ->addSelect(
                DB::raw(ScScStatus::toCaseSQL()),
                DB::raw(RpPayStatus::toCaseSQL()),
                DB::raw(RpPayStatus::toColorSQL()),
                DB::raw(RpIsValid::toCaseSQL()),
                DB::raw(RpShouldPayDate_DDD::toCaseSQL(true, 'rp.should_pay_date')),
                DB::raw((function () {
                    return sprintf(
                        "(rp.is_valid in ('%s') and  sc.sc_status in ('%s')) as can_pay",
                        join("','", [RpIsValid::VALID]),
                        join("','", [ScScStatus::PENDING, ScScStatus::SIGNED, ScScStatus::COMPLETED, ScScStatus::EARLY_TERMINATION])
                    );
                })())
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'SaleContract.contract_number' => fn ($item) => $item->contract_number,
            'Vehicle.plate_no'             => fn ($item) => $item->plate_no,
            'VehicleModel.brand_model'     => fn ($item) => $item->brand_name.'-'.$item->model_name,
            'Customer.contact_name'        => fn ($item) => $item->contact_name,
            'PaymentType.pt_name'          => fn ($item) => $item->pt_name,
            'Payment.should_pay_date'      => fn ($item) => $item->should_pay_date,
            'Payment.should_pay_amount'    => fn ($item) => $item->should_pay_amount,
            'Payment.actual_pay_date'      => fn ($item) => $item->actual_pay_date,
            'Payment.actual_pay_amount'    => fn ($item) => $item->actual_pay_amount,
            'Payment.pay_status_label'     => fn ($item) => $item->pay_status_label,
            'Payment.is_valid_label'       => fn ($item) => $item->is_valid_label,
            'SaleContract.sc_status_label' => fn ($item) => $item->sc_status_label,
            'Payment.rp_remark'            => fn ($item) => $item->rp_remark,
        ];
    }

    public static function indexStat($list): array
    {
        $accounts_receivable_amount = $actual_received_amount = $pending_receivable_amount = $pending_receivable_size = $less_receivable_amount = '0';
        foreach ($list as $key => $value) {
            if (RpIsValid::VALID == $value->is_valid) {
                $accounts_receivable_amount = bcadd($accounts_receivable_amount, $value->should_pay_amount, 2); // 应收
            }
            if (RpPayStatus::PAID == $value->pay_status) {
                $actual_received_amount = bcadd($actual_received_amount, $value->actual_pay_amount, 2); // 实收

                $less_receivable_amount = bcadd($less_receivable_amount, bcsub($value->should_pay_amount, $value->actual_pay_amount, 2), 2); // 减免
            }

            if (RpIsValid::VALID == $value->is_valid && RpPayStatus::UNPAID == $value->pay_status) {
                $pending_receivable_amount = bcadd($pending_receivable_amount, $value->should_pay_amount, 2); // 待收
                ++$pending_receivable_size; // 待收笔数
            }
        }

        return compact('accounts_receivable_amount', 'actual_received_amount', 'less_receivable_amount', 'pending_receivable_amount', 'pending_receivable_size');
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

    //    private static function rawCanPay(): string {}
    public static function importColumns(): array
    {
        return [
            'contract_number'   => [SaleContract::class, 'contract_number'],
            'pt_id'             => [Payment::class, 'pt_id'],
            'should_pay_date'   => [Payment::class, 'should_pay_date'],
            'should_pay_amount' => [Payment::class, 'should_pay_amount'],
            'pay_status'        => [Payment::class, 'pay_status'],
            'actual_pay_date'   => [Payment::class, 'actual_pay_date'],
            'actual_pay_amount' => [Payment::class, 'actual_pay_amount'],
            'pa_id'             => [Payment::class, 'pa_id'],
            'rp_remark'         => [Payment::class, 'rp_remark'],
            'vr_id'             => [Payment::class, 'vr_id'],
            'vm_id'             => [Payment::class, 'vm_id'],
            'vi_id'             => [Payment::class, 'vi_id'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['sc_id']      = SaleContract::contractNumberKv($item['contract_number'] ?? null);
            $item['pay_status'] = RpPayStatus::searchValue($item['pay_status'] ?? null);
            $item['pt_id']      = RpPtId::searchValue($item['pt_id'] ?? null);

            static::$fields['contract_number'][] = $item['contract_number'] ?? null;
            static::$fields['pa_id'][]           = $item['pa_id'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'sc_id'             => ['bail', 'required', 'integer'],
            'pt_id'             => ['bail', 'required', Rule::in(RpPtId::label_keys())],
            'should_pay_date'   => ['bail', 'required', 'date'],
            'should_pay_amount' => ['bail', 'required', 'numeric'],
            'pay_status'        => ['bail', 'required', Rule::in(RpPayStatus::label_keys())],
            'actual_pay_date'   => ['bail', Rule::requiredIf(RpPayStatus::PAID === $item['pay_status']), 'nullable', 'date'],
            'actual_pay_amount' => ['bail', Rule::requiredIf(RpPayStatus::PAID === $item['pay_status']), 'nullable', 'numeric'],
            'pa_id'             => ['bail', 'required'],
            'rp_remark'         => ['bail', 'nullable', 'string'],
        ];

        $validator = Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // contract_number
            $missing = array_diff(static::$fields['contract_number'], SaleContract::query()->pluck('contract_number')->toArray());
            if (count($missing) > 0) {
                throw new ClientException('以下合同编号不存在：'.join(',', $missing));
            }

            // pa_id
            $missing = array_diff(static::$fields['pa_id'], PaymentAccount::query()->pluck('pa_id')->toArray());
            if (count($missing) > 0) {
                throw new ClientException('以下支付账户序号不存在：'.join(',', $missing));
            }
        };
    }

    public static function importCreateDo(): \Closure
    {
        return function ($input) {
            $payment = Payment::query()->create($input);
        };
    }

    public static function options(?\Closure $where = null): array
    {
        return [];
    }

    protected function dayOfWeekName(): Attribute
    {
        return Attribute::make(
            get: fn () => Carbon::parse($this->getAttribute('should_pay_date'))->isoFormat('dddd')
        );
    }

    protected function payStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('pay_status')?->label,
        );
    }

    protected function isValidLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('is_valid')?->label,
        );
    }
}
