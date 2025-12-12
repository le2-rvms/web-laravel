<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PaPaStatus;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\ScScStatus;
use App\Enum\Vehicle\VeStatusRental;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            RpPayStatus::options(),
            SaleContract::options(
                where: function (Builder $builder) {
                    $builder->whereIn('sc.sc_status', [ScScStatus::PENDING]);
                }
            ),
        );

        if ($sc_id > 0) {
            /** @var SaleContract $saleContract */
            $saleContract = SaleContract::query()
                ->where('sc_status', '=', ScScStatus::PENDING)
                ->findOrFail($sc_id)
            ;
            $saleContract->load('Customer', 'Vehicle', 'Payments', 'Payments.SaleContract', 'Payments.PaymentType', 'SignPayments');

            // 补充 实收押金
            if (ScRentalType::LONG_TERM == $saleContract->rental_type->value) {
                $saleContract->deposit_amount_true        = $saleContract->deposit_amount ?? '0.00';
                $saleContract->management_fee_amount_true = $saleContract->management_fee_amount ?? '0.00';
                $saleContract->management_fee_amount      = $saleContract->management_fee_amount ?? '0.00';
            } elseif (ScRentalType::SHORT_TERM == $saleContract->rental_type->value) {
                $saleContract->total_rent_amount_true = $saleContract->total_rent_amount ?? '0.00';
                $saleContract->deposit_amount_true    = $saleContract->deposit_amount ?? '0.00';
            }
            $saleContract->actual_pay_date = now()->format('Y-m-d');
        } else {
            $saleContract = [];
        }

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request, SaleContract $saleContract)
    {
        $is_long_term  = ScRentalType::LONG_TERM === $saleContract->rental_type->value;
        $is_short_term = ScRentalType::SHORT_TERM === $saleContract->rental_type->value;

        $validator = Validator::make(
            $request->all(),
            [
                'deposit_amount'                  => ['bail', 'required', 'numeric'],
                'deposit_amount_true'             => ['bail', 'required', 'numeric'],
                'management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'management_fee_amount_true'      => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'decimal:0,2', 'gte:0'],
                'total_rent_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'total_rent_amount_true'          => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'insurance_base_fee_amount'       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'insurance_additional_fee_amount' => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'other_fee_amount'                => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'decimal:0,2', 'gte:0'],
                'actual_pay_date'                 => ['bail', 'required', 'date'],
                'pa_id'                           => ['bail', 'required', Rule::exists(PaymentAccount::class)->where('pa_status', PaPaStatus::ENABLED)],
            ],
            [],
            trans_property(SaleContract::class) + trans_property(Payment::class),
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($saleContract, &$vehicle, &$customer) {
                if (!$validator->failed()) {
                    if (!$saleContract->check_sc_status([ScScStatus::PENDING], $validator)) {
                        return;
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use ($saleContract, &$input) {
            foreach (RpPtId::getFeeTypes($saleContract->rental_type->value) as $label => $pt_id) {
                $should_pay_amount = $input[$label];
                $actual_pay_amount = $input[$label.'_true'] ?? null;
                if (
                    (null !== $actual_pay_amount && bccomp($actual_pay_amount, '0', 2) > 0)
                    || (null === $actual_pay_amount && bccomp($should_pay_amount, '0', 2) > 0)
                ) {
                    Payment::query()->updateOrCreate([
                        'sc_id' => $saleContract->sc_id,
                        'pt_id' => $pt_id,
                    ], [
                        'should_pay_date'   => $saleContract->rental_start,
                        'should_pay_amount' => $should_pay_amount,
                        'pay_status'        => RpPayStatus::PAID,
                        'actual_pay_date'   => $input['actual_pay_date'],
                        'actual_pay_amount' => $actual_pay_amount ?? $should_pay_amount,
                        'pa_id'             => $input['pa_id'],
                    ]);
                }
            }

            $saleContract->update([
                'sc_status' => ScScStatus::SIGNED,
                'signed_at' => now(),
            ]);

            $saleContract->Vehicle->updateStatus(status_rental: VeStatusRental::RENTED);
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
