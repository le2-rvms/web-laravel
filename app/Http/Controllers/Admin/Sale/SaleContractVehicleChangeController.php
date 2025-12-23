<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPtId;
use App\Enum\SaleContract\ScPaymentDay_Month;
use App\Enum\SaleContract\ScPaymentDay_Week;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType;
use App\Enum\SaleContract\ScRentalType_Short;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Rules\PaymentDayCheck;
use App\Services\Uploader;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('租车合同变更-车辆变更')]
class SaleContractVehicleChangeController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            ScRentalType::labelOptions(),
            ScRentalType_Short::labelOptions(),
            ScPaymentPeriod::labelOptions(),
            ScPaymentDay_Month::labelOptions(),
            ScPaymentDay_Week::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function show(Request $request, SaleContract $saleContract): Response
    {
        $input = validator([], [])->after(function ($validator) use ($saleContract) {
            $saleContract->check_status([ScStatus::EARLY_TERMINATION, ScStatus::COMPLETED], $validator); // 验证合同状态为已完成、或已提前完成
        })->validate();

        // 归零
        if (ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value) {
            $saleContract->sc_start_date   = now()->format('Y-m-d');
            $saleContract->sc_installments = null;
            $saleContract->sc_end_date     = null;
            //            $saleContract->sc_rent_amount  = null;
            $saleContract->sc_ve_id = null;
        } elseif (ScRentalType::SHORT_TERM === $saleContract->sc_rental_type->value) {
        }

        ++$saleContract->sc_version;

        //        foreach (PPtId::getFeeTypes($saleContract->sc_rental_type->value) as $pay_key => $pay_value) {
        //            $saleContract->{$pay_key} = null;
        //        }

        $saleContract->load('Customer'); // , 'Vehicle', 'Payments'

        /** @var Admin $admin */
        $admin = auth()->user();

        $this->options();
        $this->response()->withExtras(
            //            SaleContractTpl::options(),
            Vehicle::options(
                where: function (Builder $builder) use ($admin) {
                    $builder
                        ->whereIn('ve_status_rental', [VeStatusRental::LISTED])
                        ->whereIn('ve_status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                        ->when(
                            ($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids,
                            function ($query) use ($admin) {
                                $query->whereIn('ve.ve_team_id', $admin->a_team_ids)->orwhereNull('ve.ve_team_id');
                            }
                        )
                    ;
                }
            ),
            Customer::options(),
            Payment::indexList(where: function (Builder $query) use ($saleContract) {
                $query->where('p.p_sc_id', '=', $saleContract->sc_id)
                    ->where('p.p_is_valid', '=', PIsValid::VALID)
                ;
            }),
            Payment::indexStat(),
        );

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, SaleContract $saleContract): Response
    {
        $is_long_term  = ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value;
        $is_short_term = ScRentalType::SHORT_TERM === $saleContract->sc_rental_type->value;

        $input = Validator::make(
            $request->all(),
            [
                'sc_ve_id' => ['bail', 'required', 'integer'],
                //                'sc_no'         => ['bail', 'required', 'string', 'max:50', Rule::unique(SaleContract::class, 'sc_no')->ignore($saleContract->sc_no, 'sc_no')],
                'sc_free_days'  => ['bail', 'nullable', 'int:4'],
                'sc_start_date' => ['bail', 'required', 'date', 'before_or_equal:sc_end_date'],
                'sc_end_date'   => ['bail', 'required', 'date', 'after_or_equal:sc_start_date'],

                'sc_deposit_amount'        => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'sc_management_fee_amount' => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'sc_rent_amount'           => ['bail', 'required', 'numeric', 'min:0'],

                'sc_cus_1'         => ['bail', 'nullable', 'max:255'],
                'sc_cus_2'         => ['bail', 'nullable', 'max:255'],
                'sc_cus_3'         => ['bail', 'nullable', 'max:255'],
                'sc_discount_plan' => ['bail', 'nullable', 'max:255'],
                'sc_remark'        => ['bail', 'nullable', 'max:255'],
            ] + (
                $is_long_term ? [
                    'sc_installments'                => ['bail', 'required', 'integer', 'min:1'],
                    'sc_payment_day'                 => ['bail', 'required', 'integer', new PaymentDayCheck($saleContract->sc_payment_period)],
                    'payments'                       => ['bail', 'required', 'array', 'min:1'],
                    'payments.*.p_pt_id'             => ['bail', 'required', 'integer', Rule::exists(PaymentType::class, 'pt_id')],
                    'payments.*.p_should_pay_date'   => ['bail', 'required', 'date'],
                    'payments.*.p_should_pay_amount' => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                    'payments.*.p_remark'            => ['nullable', 'string'],
                ] : []
            )
            + (
                $is_short_term ? [
                    'sc_rental_days'                     => ['bail', 'required', 'nullable', 'int:4'], // 短租的
                    'sc_total_rent_amount'               => ['bail', 'required', 'numeric', 'min:0'],
                    'sc_insurance_base_fee_amount'       => ['bail', 'required', 'numeric', 'min:0'],
                    'sc_insurance_additional_fee_amount' => ['bail', 'required', 'numeric', 'min:0'],
                    'sc_other_fee_amount'                => ['bail', 'required', 'numeric', 'min:0'],
                    'sc_total_amount'                    => ['bail', 'required', 'numeric', 'min:0'],
                ] : []
            )
            + Uploader::validator_rule_upload_array('sc_additional_photos')
            + Uploader::validator_rule_upload_object('sc_additional_file'),
            [],
            trans_property(SaleContract::class) + Arr::dot(['payments' => ['*' => trans_property(Payment::class)]])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($is_short_term, $saleContract, $request) {
                if ($validator->failed()) {
                    return;
                }
                // sale_contract
                if (!$saleContract->check_status([ScStatus::EARLY_TERMINATION, ScStatus::COMPLETED], $validator)) {
                    return;
                }

                if (!$saleContract->sc_is_current_version) {
                    $validator->errors()->add('_', '原合同不是最新的');
                }

                // ve_id
                $ve_id   = $request->input('sc_ve_id');
                $vehicle = Vehicle::query()->find($ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('sc_ve_id', 'The vehicle does not exist.');

                    return;
                }

                $vehicle_pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::LISTED, VeStatusRental::RESERVED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                if (!$vehicle_pass) {
                    return;
                }

                $cu_id    = $request->input('sc_cu_id');
                $customer = Customer::query()->find($cu_id);
                if (!$customer) {
                    $validator->errors()->add('sc_cu_id', 'The customer does not exist.');

                    return;
                }

                if ($is_short_term) {
                    if (0 !== bccomp(
                        $request->input('sc_total_rent_amount'),
                        bcmul(
                            $request->input('sc_rent_amount'),
                            bcsub($request->input('sc_rental_days'), $request->input('sc_free_days'), 0),
                            2
                        ),
                        2
                    )) {
                        $validator->errors()->add('sc_total_rent_amount', '总租金计算错误');
                    }

                    if (0 !== bccomp(
                        math_array_bcadd(2, $request->input('sc_deposit_amount'), $request->input('sc_management_fee_amount'), $request->input('sc_total_rent_amount'), $request->input('sc_insurance_base_fee_amount'), $request->input('sc_insurance_additional_fee_amount'), $request->input('sc_other_fee_amount')),
                        $request->input('sc_total_amount'),
                        2
                    )) {
                        $validator->errors()->add('sc_total_amount', '总金额计算错误');
                    }
                }
            })->validate()
        ;

        $input = $input + [
            'sc_rental_type'        => $saleContract->sc_rental_type,
            'sc_payment_period'     => $saleContract->sc_payment_period,
            'sc_cu_id'              => $saleContract->sc_cu_id,
            'sc_order_at'           => now(),
            'sc_status'             => ScStatus::PENDING,
            'sc_no'                 => $saleContract->sc_no,
            'sc_version'            => $saleContract->sc_version + 1,
            'sc_is_current_version' => false,
        ]
        + ($is_long_term ? [
            // 总计租金金额
            'sc_total_rent_amount' => $sc_total_rent_amount = bcmul($input['sc_installments'], $input['sc_rent_amount'], 2),

            // 总计金额
            'sc_total_amount' => math_array_bcadd(2, $sc_total_rent_amount, $input['sc_deposit_amount'], $input['sc_management_fee_amount']),

            // 租期天数 rental_days
            'sc_rental_days' => Carbon::parse($input['sc_start_date'])->diffInDays(Carbon::parse($input['sc_end_date']), true) + 1,
        ] : []);

        $saleContract_new = null;
        DB::transaction(function () use ($is_long_term, &$input, &$saleContract_new) {
            /** @var SaleContract $saleContract */
            $saleContract_new = SaleContract::query()->create($input);

            //            else {
            //                // 有修改车辆，前提是必须是在初始阶段，要把原车辆的状态改回来。
            //                if ($saleContract->sc_ve_id !== $input['ve_id']) {
            //                    $saleContract->Vehicle->updateStatus(ve_status_rental: VeStatusRental::LISTED);
            //                }
            //
            //                $saleContract->update($input);
            //            }

            if ($is_long_term) {
                //                $saleContract->Payments()->delete();

                foreach ($input['payments'] as $payment) {
                    $saleContract_new->Payments()->create($payment);
                }
            }

            $saleContract_new->Vehicle->updateStatus(ve_status_rental: VeStatusRental::RESERVED);
        });

        //        $saleContract->refresh()->load('Payments');

        return $this->response()->withData($saleContract_new)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function paymentsOption(Request $request): Response
    {
        /** @var SaleContractController $controller */
        $controller = \app(SaleContractController::class);

        return $controller->paymentsOption($request);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'sale_contract',
            [
                'sc_additional_photos',
                'sc_additional_file',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            ScRentalType::options(),
            $with_group_count ? ScRentalType_Short::options_with_count(SaleContract::class) : ScRentalType_Short::options(),
            ScPaymentPeriod::options(),
            ScPaymentDay_Month::options(),
            ScPaymentDay_Week::options(),
            $with_group_count ? ScStatus::options_with_count(SaleContract::class) : ScStatus::options(),
        );
    }
}
