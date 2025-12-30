<?php

namespace App\Models\Payment;

use App\Attributes\ClassName;
use App\Attributes\ColumnDesc;
use App\Attributes\ColumnType;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\Payment\PShouldPayDate_DDD;
use App\Enum\SaleContract\ScStatus;
use App\Exceptions\ClientException;
use App\Models\_\ImportTrait;
use App\Models\_\ModelTrait;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleRepair;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[ClassName('财务', '记录')]
#[ColumnDesc('p_id', required: true, )]
#[ColumnDesc('p_sc_id', required: true, )]
#[ColumnDesc('p_pt_id', required: true, enum_class: PPtId::class)]
#[ColumnDesc('p_should_pay_date', required: true, type: ColumnType::DATE)]
#[ColumnDesc('p_should_pay_amount', required: true, )]
#[ColumnDesc('p_pay_status', required: true, enum_class: PPayStatus::class)]
#[ColumnDesc('p_actual_pay_date', type: ColumnType::DATE)]
#[ColumnDesc('p_actual_pay_amount')]
#[ColumnDesc('p_pa_id')]
#[ColumnDesc('p_remark')]
#[ColumnDesc('p_is_valid', required: true, enum_class: PIsValid::class)]
#[ColumnDesc('p_vr_id')]
#[ColumnDesc('p_vm_id')]
#[ColumnDesc('p_vi_id')]
/**
 * @property int               $p_id                财务记录序号
 * @property int               $p_sc_id             租车合同序号
 * @property int               $p_pt_id             付款类型序号；引用payment_types表
 * @property Carbon|string     $p_should_pay_date   应收/应付日期
 * @property string            $p_day_of_week_name  应收/应付星期
 * @property float             $p_should_pay_amount 应收/付金额
 * @property PPayStatus|string $p_pay_status        付款状态；例如已支付、未支付、失败等
 * @property null|Carbon       $p_actual_pay_date   实际收付日期
 * @property null|float        $p_actual_pay_amount 实际收付金额
 * @property null|int          $p_pa_id             支付账户序号
 * @property null|string       $p_remark            财务备注
 * @property int|PIsValid      $p_is_valid          是否有效
 * @property null              $p_vr_id             车辆维修序号
 * @property null              $p_vm_id             车辆维修序号
 * @property null              $p_vi_id             车辆检查序号
 * @property array|mixed       $p_period            ;start_d 、end_d
 * @property SaleContract      $SaleContract
 * @property PaymentAccount    $PaymentAccount
 * @property PaymentType       $PaymentType
 * @property null|string       $p_pay_status_label  支付状态-中文
 * @property null|string       $p_is_valid_label    有效状态-中文
 */
class Payment extends Model
{
    use ModelTrait;

    use ImportTrait;

    public const CREATED_AT = 'p_created_at';
    public const UPDATED_AT = 'p_updated_at';
    public const UPDATED_BY = 'p_updated_by';

    protected $primaryKey = 'p_id';

    protected $guarded = ['p_id'];

    protected $attributes = [
        'p_pay_status' => PPayStatus::UNPAID,
        'p_is_valid'   => PIsValid::VALID,
    ];

    protected $casts = [
        'p_pay_status'        => PPayStatus::class,
        'p_should_pay_amount' => 'decimal:2',
        'p_actual_pay_amount' => 'decimal:2',
        'p_is_valid'          => PIsValid::class,
        //        'p_pt_id'             => RpPtId::class, // !!! 因为是外键，所以不能做转换。
    ];

    protected $appends = [
        'p_day_of_week_name',
        'p_pay_status_label',
        'p_is_valid_label',
    ];

    public function SaleContract(): BelongsTo
    {
        return $this->belongsTo(SaleContract::class, 'p_sc_id', 'sc_id');
    }

    public function PaymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'p_pt_id', 'pt_id');
    }

    public function PaymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class, 'p_pa_id', 'pa_id');
    }

    public function VehicleRepair(): BelongsTo
    {
        return $this->belongsTo(VehicleRepair::class, 'p_vr_id', 'vr_id');
    }

    public function VehicleMaintenance(): BelongsTo
    {
        return $this->belongsTo(VehicleMaintenance::class, 'p_vm_id', 'vm_id');
    }

    public function VehicleInspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'p_vi_id', 'vi_id');
    }

    public static function indexQuery(): Builder
    {
        return static::query()
            ->from('payments', 'p')
            ->leftJoin('payment_accounts as pa', 'pa.pa_id', '=', 'p.p_pa_id')
            ->leftJoin('payment_types as pt', 'pt.pt_id', '=', 'p.p_pt_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'p.p_sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.sc_cu_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.sc_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->select('*')
            ->addSelect(
                DB::raw(ScStatus::toCaseSQL()),
                DB::raw(PPayStatus::toCaseSQL()),
                DB::raw(PPayStatus::toColorSQL()),
                DB::raw(PIsValid::toCaseSQL()),
                DB::raw(PIsValid::toColorSQL()),
                DB::raw(PShouldPayDate_DDD::toCaseSQL(true, 'p.p_should_pay_date')),
                // 业务规则：仅有效记录且合同状态处于可收款范围内时标记为可收。
                DB::raw((function () {
                    return sprintf(
                        "(p.p_is_valid in ('%s') and  sc.sc_status in ('%s')) as can_pay",
                        join("','", [PIsValid::VALID]),
                        join("','", [ScStatus::PENDING, ScStatus::SIGNED, ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION])
                    );
                })())
            )
        ;
    }

    public static function indexColumns(): array
    {
        return [
            'SaleContract.sc_no'           => fn ($item) => $item->sc_no,
            'Vehicle.plate_no'             => fn ($item) => $item->plate_no,
            'VehicleModel.brand_model'     => fn ($item) => $item->vm_brand_name.'-'.$item->vm_model_name,
            'Customer.cu_contact_name'     => fn ($item) => $item->cu_contact_name,
            'PaymentType.pt_name'          => fn ($item) => $item->pt_name,
            'Payment.p_should_pay_date'    => fn ($item) => $item->p_should_pay_date,
            'Payment.p_should_pay_amount'  => fn ($item) => $item->p_should_pay_amount,
            'Payment.p_actual_pay_date'    => fn ($item) => $item->p_actual_pay_date,
            'Payment.p_actual_pay_amount'  => fn ($item) => $item->p_actual_pay_amount,
            'Payment.pay_status_label'     => fn ($item) => $item->pay_status_label,
            'Payment.is_valid_label'       => fn ($item) => $item->is_valid_label,
            'SaleContract.sc_status_label' => fn ($item) => $item->sc_status_label,
            'Payment.p_remark'             => fn ($item) => $item->p_remark,
        ];
    }

    public static function indexStatValue($list): array
    {
        $accounts_receivable_amount = $actual_received_amount = $pending_receivable_amount = $pending_receivable_size = $less_receivable_amount = '0';
        foreach ($list as $key => $value) {
            /** @var Payment $value */
            if (PIsValid::VALID == $value->p_is_valid->value) {
                $accounts_receivable_amount = bcadd($accounts_receivable_amount, $value->p_should_pay_amount, 2); // 应收
            }
            if (PPayStatus::PAID == $value->p_pay_status->value) {
                $actual_received_amount = bcadd($actual_received_amount, $value->p_actual_pay_amount, 2); // 实收

                $less_receivable_amount = bcadd($less_receivable_amount, bcsub($value->p_should_pay_amount, $value->p_actual_pay_amount, 2), 2); // 减免
            }

            if (PIsValid::VALID == $value->p_is_valid->value && PPayStatus::UNPAID == $value->p_pay_status->value) {
                $pending_receivable_amount = bcadd($pending_receivable_amount, $value->p_should_pay_amount, 2); // 待收
                ++$pending_receivable_size; // 待收笔数
            }
        }

        return compact('accounts_receivable_amount', 'actual_received_amount', 'less_receivable_amount', 'pending_receivable_amount', 'pending_receivable_size');
    }

    public static function option(Collection $Payments): array
    {
        $key = static::getOptionKey();

        return [
            $key => (function () use ($Payments) {
                $value = [];
                foreach ($Payments as $key => $rp) {
                    $value[] = ['text' => $rp->p_remark, 'value' => $key];
                }

                return $value;
            })(),
        ];
    }

    //    private static function rawCanPay(): string {}
    public static function importColumns(): array
    {
        return [
            'sc_no'               => [SaleContract::class, 'sc_no'],
            'p_pt_id'             => [Payment::class, 'p_pt_id'],
            'p_should_pay_date'   => [Payment::class, 'p_should_pay_date'],
            'p_should_pay_amount' => [Payment::class, 'p_should_pay_amount'],
            'p_pay_status'        => [Payment::class, 'p_pay_status'],
            'p_actual_pay_date'   => [Payment::class, 'p_actual_pay_date'],
            'p_actual_pay_amount' => [Payment::class, 'p_actual_pay_amount'],
            'p_pa_id'             => [Payment::class, 'p_pa_id'],
            'p_remark'            => [Payment::class, 'p_remark'],
            'p_vr_id'             => [Payment::class, 'p_vr_id'],
            'p_vm_id'             => [Payment::class, 'p_vm_id'],
            'p_vi_id'             => [Payment::class, 'p_vi_id'],
        ];
    }

    public static function importBeforeValidateDo(): \Closure
    {
        return function (&$item) {
            $item['p_sc_id']      = SaleContract::contractNumberKv($item['sc_no'] ?? null);
            $item['p_pay_status'] = PPayStatus::searchValue($item['p_pay_status'] ?? null);
            $item['p_pt_id']      = PPtId::searchValue($item['p_pt_id'] ?? null);

            static::$fields['sc_no'][]   = $item['sc_no'] ?? null;
            static::$fields['p_pa_id'][] = $item['p_pa_id'] ?? null;
        };
    }

    public static function importValidatorRule(array $item, array $fieldAttributes): void
    {
        $rules = [
            'p_sc_id'             => ['bail', 'required', 'integer'],
            'p_pt_id'             => ['bail', 'required', Rule::in(PPtId::label_keys())],
            'p_should_pay_date'   => ['bail', 'required', 'date'],
            'p_should_pay_amount' => ['bail', 'required', 'numeric'],
            'p_pay_status'        => ['bail', 'required', Rule::in(PPayStatus::label_keys())],
            'p_actual_pay_date'   => ['bail', Rule::requiredIf(PPayStatus::PAID === $item['p_pay_status']), 'nullable', 'date'],
            'p_actual_pay_amount' => ['bail', Rule::requiredIf(PPayStatus::PAID === $item['p_pay_status']), 'nullable', 'numeric'],
            'p_pa_id'             => ['bail', 'required'],
            'p_remark'            => ['bail', 'nullable', 'string'],
        ];

        $validator = Validator::make($item, $rules, [], $fieldAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function importAfterValidatorDo(): \Closure
    {
        return function () {
            // sc_no
            $missing = array_diff(static::$fields['sc_no'], SaleContract::query()->pluck('sc_no')->toArray());
            if (count($missing) > 0) {
                throw new ClientException('以下合同编号不存在：'.join(',', $missing));
            }

            // p_pa_id
            $missing = array_diff(static::$fields['p_pa_id'], PaymentAccount::query()->pluck('pa_id')->toArray());
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

    public static function optionsQuery(): Builder
    {
        return static::query();
    }

    protected function pDayOfWeekName(): Attribute
    {
        return Attribute::make(
            get: fn () => Carbon::parse($this->getAttribute('p_should_pay_date'))->isoFormat('dddd')
        );
    }

    protected function pPayStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('p_pay_status')?->label,
        );
    }

    protected function pIsValidLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttribute('p_is_valid')?->label,
        );
    }
}
