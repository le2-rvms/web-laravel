<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PaStatus;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusRental;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('签约收款')]
class SaleContractSignPaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index() {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(string $sc_id): Response
    {
        abort_if(!is_numeric($sc_id), 404);

        $this->response()->withExtras(
            PaymentAccount::options(),
            PPayStatus::options(),
            SaleContract::options(
                where: function (Builder $builder) {
                    $builder
                        ->whereIn('sc.sc_status', [ScStatus::PENDING])
                    ;
                }
            ),
        );

        if ($sc_id > 0) {
            /** @var SaleContract $saleContract */
            $saleContract = SaleContract::query()
                ->where('sc_status', '=', ScStatus::PENDING)
                ->findOrFail($sc_id)
            ;
            $saleContract->load('Customer', 'Vehicle', 'Payments', 'Payments.SaleContract', 'Payments.PaymentType', 'SignPayments');

            // 补充「实收」字段，便于前端直接回显。
            if (ScRentalType::LONG_TERM == $saleContract->sc_rental_type->value) {
                $saleContract->sc_deposit_amount_true        = $saleContract->sc_deposit_amount ?? '0.00';
                $saleContract->sc_management_fee_amount_true = $saleContract->sc_management_fee_amount ?? '0.00';
                $saleContract->sc_management_fee_amount      = $saleContract->sc_management_fee_amount ?? '0.00';
            } elseif (ScRentalType::SHORT_TERM == $saleContract->sc_rental_type->value) {
                $saleContract->sc_total_rent_amount_true = $saleContract->sc_total_rent_amount ?? '0.00';
                $saleContract->sc_deposit_amount_true    = $saleContract->sc_deposit_amount ?? '0.00';
            }
            $saleContract->sc_actual_pay_date = now()->format('Y-m-d');

            // 查找可用的退押金记录，用于押金转移支付。

            $payment_refund_deposit = Payment::indexQuery()
//                    ->where('p_sc_id', '=', $saleContractPre->sc_id)
                ->where('sc_cu_id', '=', $saleContract->sc_cu_id)
                ->where('p_pt_id', '=', PPtId::REFUND_DEPOSIT)
                ->where('p_is_valid', '=', PIsValid::VALID)
                ->where('p_pay_status', '=', PPayStatus::UNPAID)
                ->addSelect(DB::raw('1 as checked')) // 默认选中
                ->first()
            ;

            $saleContract->payment_refund_deposit = $payment_refund_deposit;
        //            }
        } else {
            $saleContract = [];
        }

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request, SaleContract $saleContract)
    {
        $is_long_term  = ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value;
        $is_short_term = ScRentalType::SHORT_TERM === $saleContract->sc_rental_type->value;

        /** @var Payment $payment_refund_deposit */
        $payment_refund_deposit = null;

        $input = Validator::make(
            $request->all(),
            [
                'sc_deposit_amount'                          => ['bail', 'required', 'numeric'],
                'sc_deposit_amount_true'                     => ['bail', 'required', 'numeric'],
                'sc_management_fee_amount'                   => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'sc_management_fee_amount_true'              => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'decimal:0,2', 'gte:0'],
                'sc_total_rent_amount'                       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'sc_total_rent_amount_true'                  => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'sc_insurance_base_fee_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'sc_insurance_additional_fee_amount'         => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'sc_other_fee_amount'                        => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'sc_actual_pay_date'                         => ['bail', 'required', 'date'],
                'p_pa_id'                                    => ['bail', 'required', Rule::exists(PaymentAccount::class, 'pa_id')->where('pa_status', PaStatus::ENABLED)],
                'payment_refund_deposit'                     => ['bail', 'nullable', 'array'],
                'payment_refund_deposit.checked'             => ['bail', 'required_with:payment_refund_deposit', 'boolean'],
                'payment_refund_deposit.p_id'                => ['bail', 'required_with:payment_refund_deposit', Rule::exists(Payment::class, 'p_id')],
                'payment_refund_deposit.p_should_pay_amount' => ['bail', 'required_with:payment_refund_deposit', 'decimal:0,2', 'lte:0'],
            ],
            [],
            trans_property(SaleContract::class) + trans_property(Payment::class),
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, $saleContract, &$vehicle, &$customer, &$payment_refund_deposit) {
                if ($validator->failed()) {
                    return;
                }
                if (!$saleContract->check_status([ScStatus::PENDING], $validator)) {
                    return;
                }

                // 押金转移支付校验：记录必须与当前客户匹配且金额一致。
                if ($request->boolean('payment_refund_deposit.checked')) {
                    $payment_refund_deposit = Payment::query()
                        ->whereHas('SaleContract', function (Builder $query) use ($saleContract) {
                            $query->where('sc_cu_id', '=', $saleContract->sc_cu_id);
                        })
//                        ->where('p_sc_id', '=', $saleContractPre->sc_id)
//                        ->where('sc_cu_id', '=', $saleContract->sc_cu_id)
                        ->where('p_pt_id', '=', PPtId::REFUND_DEPOSIT)
                        ->where('p_is_valid', '=', PIsValid::VALID)
                        ->where('p_pay_status', '=', PPayStatus::UNPAID)
                        ->first()
                    ;

                    if ($payment_refund_deposit && (
                        $request->json('payment_refund_deposit.p_id') !== $payment_refund_deposit->p_id
                        || $request->json('payment_refund_deposit.p_should_pay_amount' !== $payment_refund_deposit->p_should_pay_amount)
                    )) {
                        $validator->errors()->add('payment_refund_deposit.p_id', '押金转移支付错误');

                        return;
                    }
                }
            })
            ->validate()
        ;

        DB::transaction(function () use (&$payment_refund_deposit, $saleContract, &$input) {
            // 关键一致性：押金转移需在同一事务内完成（原退押金置为无需支付 + 新建抵扣收款），
            // 抵扣记录使用负数实际金额用于对冲原押金。
            if ($input['payment_refund_deposit']['checked'] ?? false) {
                // 将退押金记录改为无需支付，并生成一笔押金收款抵扣。
                $payment_refund_deposit->update([
                    'p_pay_status'        => PPayStatus::NO_NEED_PAY,
                    'p_actual_pay_date'   => $saleContract->sc_start_date,
                    'p_actual_pay_amount' => $payment_refund_deposit->p_should_pay_amount,
                ]);

                // 直接创建记录不会触发 PaymentObserver，因此在这里补齐字段。
                Payment::query()->create([
                    'p_sc_id'             => $saleContract->sc_id,
                    'p_pt_id'             => PPtId::DEPOSIT,
                    'p_should_pay_date'   => $saleContract->sc_start_date,
                    'p_should_pay_amount' => '0',
                    'p_pay_status'        => PPayStatus::NO_NEED_PAY,
                    'p_actual_pay_date'   => $payment_refund_deposit->p_actual_pay_date,
                    'p_actual_pay_amount' => bcsub('0', $payment_refund_deposit->p_actual_pay_amount, 2),
                    'p_pa_id'             => $input['p_pa_id'],
                    'p_is_valid'          => PIsValid::VALID,
                ]);
            }

            foreach (PPtId::getFeeTypes($saleContract->sc_rental_type->value) as $label => $pt_id) {
                $should_pay_amount = $input[$label];
                $actual_pay_amount = $input[$label.'_true'] ?? null;
                if ((null !== $actual_pay_amount && bccomp($actual_pay_amount, '0', 2) > 0) || (null === $actual_pay_amount && bccomp($should_pay_amount, '0', 2) > 0)) {
                    // 仅对有金额的费用项生成收款记录。
                    Payment::query()->updateOrCreate([
                        'p_sc_id' => $saleContract->sc_id,
                        'p_pt_id' => $pt_id,
                    ], [
                        'p_should_pay_date'   => $saleContract->sc_start_date,
                        'p_should_pay_amount' => $should_pay_amount,
                        'p_pay_status'        => PPayStatus::PAID,
                        'p_actual_pay_date'   => $input['sc_actual_pay_date'],
                        'p_actual_pay_amount' => $actual_pay_amount ?? $should_pay_amount,
                        'p_pa_id'             => $input['p_pa_id'],
                    ]);
                }
            }

            $saleContract->update([
                'sc_status'    => ScStatus::SIGNED,
                'sc_signed_at' => now(),
            ]);

            // 签约完成后车辆进入租赁状态。
            $saleContract->Vehicle->updateStatus(ve_status_rental: VeStatusRental::RENTED);
        });

        return $this->response()->withData($input)->respond();
    }

    public function show(Payment $payment) {}

    public function edit(Payment $payment) {}

    public function update(Request $request, SaleContract $saleContract) {}

    public function destroy(Payment $payment) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
