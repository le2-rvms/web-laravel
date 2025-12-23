<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Payment\PPtId;
use App\Enum\Sale\DtExportType;
use App\Enum\Sale\DtStatus;
use App\Enum\Sale\DtType;
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
use App\Models\Admin\AdminRole;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleContract;
use App\Models\Sale\SaleContractTpl;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleTmp;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleUsage;
use App\Models\Vehicle\VehicleViolation;
use App\Rules\PaymentDayCheck;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('租车合同')]
class SaleContractController extends Controller
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

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Customer::options(),
            Vehicle::options(),
        );

        $query   = SaleContract::indexQuery();
        $columns = SaleContract::indexColumns();

        /** @var Admin $admin */
        $admin = auth()->user();

        // 车队查询条件
        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('cu.cu_team_id', $admin->a_team_ids)->orWhereNull('cu.cu_team_id');
            });
        }

        // 如果是管理员或经理，则可以看到所有的用户；如果不是管理员或经理，则只能看到销售或驾管为自己的用户。
        $role_sales_manager = $admin->hasRole(AdminRole::role_sales);
        if ($role_sales_manager) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereNull('cu.cu_sales_manager')->orWhere('cu.cu_sales_manager', '=', $admin->id);
            });
        }

        $paginate = new PaginateService(
            [],
            [['sc.sc_id', 'desc']],
            ['kw', 'sc_status', 'sc_ve_id', 'sc_cu_id', 'sc_start_date', 'sc_rental_type', 'sc_is_current_version'], // todo  start_date 的范围是整体的
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('sc.sc_no', 'like', '%'.$value.'%')
                            ->orWhere('ve.ve_plate_no', 'like', '%'.$value.'%')
                            ->orWhere('cu.cu_contact_name', 'like', '%'.$value.'%')
                            ->orWhere('sc.sc_remark', 'like', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $saleContract = new SaleContract([
            'sc_rental_type' => ScRentalType::LONG_TERM,
            'sc_start_date'  => date('Y-m-d'),
        ]);

        /** @var Admin $admin */
        $admin = auth()->user();

        $this->options();
        $this->response()->withExtras(
            SaleContractTpl::options(),
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
        );

        return $this->response()->withData($saleContract)->respond();
    }

    /**
     * @throws ValidationException
     */
    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(SaleContract $saleContract): Response
    {
        $saleContract->load('Customer', 'Vehicle', 'Payments');

        $this->response()->withExtras(
            VehicleTmp::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleInspection::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            Payment::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            Payment::indexStat(),
            SaleSettlement::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleUsage::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleRepair::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleRepair::indexStat(),
            VehicleViolation::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleManualViolation::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
        );

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(SaleContract $saleContract): Response
    {
        $saleContract->load('Customer', 'Vehicle', 'Payments');

        $this->options();

        $this->response()->withExtras(
            SaleContractTpl::options(),
            Vehicle::options(
                where: function (Builder $builder) use ($saleContract) {
                    $builder->where(function (Builder $query) {
                        $query->whereIn('ve_status_rental', [VeStatusRental::LISTED])
                            ->whereIn('ve_status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                        ;
                    })->when(VeStatusRental::PENDING === $saleContract->sc_status->value, function (Builder $query) use ($saleContract) {
                        $query->orWhere('ve.ve_id', '=', $saleContract->sc_ve_id); // 显示出原车辆
                    });
                }
            ),
            Customer::options(),
        );

        $this->response()->withExtras(
            VehicleTmp::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleInspection::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            Payment::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            Payment::indexStat(),
            SaleSettlement::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleUsage::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleRepair::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleRepair::indexStat(),
            VehicleViolation::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleViolation::indexStat(),
            VehicleManualViolation::indexList(where: function (Builder $query) use ($saleContract) { $query->where('sc.sc_id', '=', $saleContract->sc_id); }),
            VehicleManualViolation::indexStat(),
            DocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtType::SALE_CONTRACT);
            }),
        );

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, SaleContract $saleContract, DocTplService $docTplService)
    {
        $input = $request->validate([
            'dt_export_type' => ['required', Rule::in(DtExportType::label_keys())],
            'dt_id'          => ['required', Rule::exists(DocTpl::class, 'dt_id')->where('dt_type', DtType::SALE_CONTRACT)->where('dt_status', DtStatus::ENABLED)],
        ]);

        $saleContract->load('Customer', 'Vehicle', 'Vehicle.VehicleInsurances', 'Company', 'Payments'); // 'Company',

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['dt_export_type'], $saleContract);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?SaleContract $saleContract): Response
    {
        $input1 = $request->validate(
            [
                'sc_rental_type' => ['bail', 'required', Rule::in(ScRentalType::label_keys())],
            ],
            [],
            trans_property(SaleContract::class)
        );
        $rental_type = $input1['sc_rental_type'];

        $is_long_term  = ScRentalType::LONG_TERM === $rental_type;
        $is_short_term = ScRentalType::SHORT_TERM === $rental_type;

        $input2 = $request->validate(
            [
                'sc_payment_period' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'string', Rule::in(ScPaymentPeriod::label_keys())],
            ],
            [],
            trans_property(SaleContract::class)
        );

        $sc_payment_period = $input2['sc_payment_period'] ?? null;

        $input = Validator::make(
            $request->all(),
            [
                'sc_cu_id'        => ['bail', 'required', 'integer'],
                'sc_ve_id'        => ['bail', 'required', 'integer'],
                'sc_no'           => ['bail', 'required', 'string', 'max:50', Rule::unique(SaleContract::class, 'sc_no')->ignore($saleContract)],
                'sc_free_days'    => ['bail', 'nullable', 'int:4'],
                'sc_start_date'   => ['bail', 'required', 'date', 'before_or_equal:sc_end_date'],
                'sc_installments' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', 'min:1'],
                'sc_rental_days'  => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'nullable', 'int:4'], // 短租的
                'sc_end_date'     => ['bail', 'required', 'date', 'after_or_equal:sc_start_date'],

                'sc_deposit_amount'                  => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'sc_management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'sc_rent_amount'                     => ['bail', 'required', 'numeric', 'min:0'],
                'sc_payment_day'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', new PaymentDayCheck($sc_payment_period)],
                'sc_total_rent_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'sc_insurance_base_fee_amount'       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'sc_insurance_additional_fee_amount' => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'sc_other_fee_amount'                => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'sc_total_amount'                    => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],

                'sc_cus_1'                       => ['bail', 'nullable', 'max:255'],
                'sc_cus_2'                       => ['bail', 'nullable', 'max:255'],
                'sc_cus_3'                       => ['bail', 'nullable', 'max:255'],
                'sc_discount_plan'               => ['bail', 'nullable', 'max:255'],
                'sc_remark'                      => ['bail', 'nullable', 'max:255'],
                'payments'                       => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'array', 'min:1'],
                'payments.*.p_pt_id'             => ['bail', 'required', 'integer', Rule::exists(PaymentType::class, 'pt_id')],
                'payments.*.p_should_pay_date'   => ['bail', 'required', 'date'],
                'payments.*.p_should_pay_amount' => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'payments.*.p_remark'            => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('sc_additional_photos')
            + Uploader::validator_rule_upload_object('sc_additional_file'),
            [],
            trans_property(SaleContract::class) + Arr::dot(['payments' => ['*' => trans_property(Payment::class)]])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($is_short_term, $saleContract, $request) {
                if ($validator->failed()) {
                    return;
                }
                // ve_id
                $sc_ve_id = $request->input('sc_ve_id');
                $vehicle  = Vehicle::query()->find($sc_ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                    return;
                }

                $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::LISTED, VeStatusRental::RESERVED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                if (!$pass) {
                    return;
                }

                // 当车辆变化时候，状态必须是待签约
                if ($saleContract && $saleContract->sc_ve_id !== $sc_ve_id) {
                    if (ScStatus::PENDING !== $saleContract->sc_status->value) {
                        $validator->errors()->add('ve_id', '合同在待签约状态下才允许修改车辆。');

                        return;
                    }
                }

                $sc_cu_id = $request->input('sc_cu_id');
                $customer = Customer::query()->find($sc_cu_id);
                if (!$customer) {
                    $validator->errors()->add('cu_id', 'The customer does not exist.');

                    return;
                }

                if ($saleContract) {
                    if (!$saleContract->check_status([ScStatus::PENDING], $validator)) {
                        return;
                    }
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
                        $validator->errors()->add('total_rent_amount', '总租金计算错误');
                    }

                    if (0 !== bccomp(
                        math_array_bcadd(2, $request->input('sc_deposit_amount'), $request->input('sc_management_fee_amount'), $request->input('sc_total_rent_amount'), $request->input('sc_insurance_base_fee_amount'), $request->input('sc_insurance_additional_fee_amount'), $request->input('sc_other_fee_amount')),
                        $request->input('sc_total_amount'),
                        2
                    )) {
                        $validator->errors()->add('sc_total_amount', '总金额计算错误');
                    }
                }
            })
            ->validate()
        ;

        $input = $input1 + $input2 + $input;

        // input数据修正
        if (ScRentalType::LONG_TERM === $input['sc_rental_type']) {
            // 总计租金金额
            $input['sc_total_rent_amount'] = bcmul($input['sc_installments'], $input['sc_rent_amount'], 2);

            // 总计金额
            $input['sc_total_amount'] = math_array_bcadd(2, $input['sc_total_rent_amount'], $input['sc_deposit_amount'], $input['sc_management_fee_amount']);

            // 租期天数 rental_days
            $input['sc_rental_days'] = Carbon::parse($input['sc_start_date'])->diffInDays(Carbon::parse($input['sc_end_date']), true) + 1;
        }

        DB::transaction(function () use (&$input, &$saleContract) {
            /** @var SaleContract $saleContract */
            if (null === $saleContract) {
                $saleContract = SaleContract::query()->create($input + [
                    'sc_order_at'           => now(),
                    'sc_status'             => ScStatus::PENDING,
                    'sc_version'            => 1,
                    'sc_is_current_version' => true,
                ]);
            } else {
                // 有修改车辆，前提是必须是在初始阶段，要把原车辆的状态改回来。
                if ($saleContract->sc_ve_id !== $input['sc_ve_id']) {
                    $saleContract->Vehicle->updateStatus(ve_status_rental: VeStatusRental::LISTED);
                }

                $saleContract->update($input);
            }

            if (ScRentalType::LONG_TERM === $saleContract->sc_rental_type->value) {
                $saleContract->Payments()->delete();

                foreach ($input['payments'] as $payment) {
                    $saleContract->Payments()->create($payment);
                }
            }

            $saleContract->Vehicle->updateStatus(ve_status_rental: VeStatusRental::RESERVED);
        });

        $saleContract->refresh()->load('Payments');

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(SaleContract $saleContract): Response
    {
        $saleContract->delete();

        return $this->response()->withData($saleContract)->respond();
    }

    /**
     * 生成付款计划.
     */
    #[PermissionAction(PermissionAction::WRITE)]
    public function paymentsOption(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'sc_rental_type'           => ['bail', 'required', Rule::in([ScRentalType::LONG_TERM])],
                'sc_payment_period'        => ['bail', 'required', 'string', Rule::in(ScPaymentPeriod::label_keys())],
                'sc_deposit_amount'        => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'sc_management_fee_amount' => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'sc_start_date'            => ['bail', 'required', 'date'],
                'sc_installments'          => ['bail', 'required', 'integer', 'min:1'],
                'sc_rent_amount'           => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'sc_payment_day'           => ['bail', 'required', 'integer', new PaymentDayCheck($request->input('sc_payment_period'))],
            ],
            [],
            trans_property(SaleContract::class)
        )
            ->validate()
        ;

        // 创建数字到星期名称的映射
        $daysOfWeek = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        $paymentType = $input['sc_payment_period'];

        list('interval' => $interval, 'interval_unit' => $interval_unit, 'prepaid' => $prepaid) = ScPaymentPeriod::interval[$paymentType];

        $paymentPeriod = ScPaymentPeriod::payment_day_classes[$paymentType];

        $startDate = Carbon::parse($input['sc_start_date']);

        $free_days = $request->input('free_days');

        $schedule = new Collection();

        // 添加一次性押金
        $schedule->push(new Payment([
            'p_pt_id'             => PPtId::DEPOSIT,
            'p_should_pay_date'   => $startDate->toDateString(),
            'p_should_pay_amount' => $input['sc_deposit_amount'],
            'p_remark'            => new PPtId(PPtId::DEPOSIT)->label,
        ]));

        if (($sc_management_fee_amount = ($input['sc_management_fee_amount'] ?? null)) && $input['sc_management_fee_amount'] > 0) {// 添加一次性管理费（如果有）
            $schedule->push(new Payment([
                'p_pt_id'             => PPtId::MANAGEMENT_FEE,
                'p_should_pay_date'   => $startDate->toDateString(),
                'p_should_pay_amount' => $sc_management_fee_amount,
                'p_remark'            => new PPtId(PPtId::MANAGEMENT_FEE)->label,
            ]));
        }

        $installmentNumber = 1;

        $currentDate = $startDate->copy();

        while (true) {
            // 计算账单周期
            $billingPeriodStart = $currentDate->copy();
            $billingPeriodEnd   = $currentDate->copy()->add($interval.' '.$interval_unit)->subDay();

            if (1 === $installmentNumber && $free_days > 0) {
                $billingPeriodEnd->add(CarbonInterval::days($free_days));
            }

            // 如果账单周期开始日期超过结束日期，或者付款日期超过租赁结束日期，跳出循环
            if ($billingPeriodStart->greaterThan($billingPeriodEnd)) {
                break;
            }

            if ($prepaid) {
                $paymentDate = $billingPeriodStart->copy(); // 预付的付款日期为账单周期开始日期
            } else {
                $paymentDate = $billingPeriodEnd->copy();
            }

            // 调整付款日期
            if (ScPaymentDay_Week::class == $paymentPeriod) {
                if ($prepaid) {
                    if (1 !== $installmentNumber) {
                        // 获取指定的星期名称
                        $dayName = $daysOfWeek[$input['sc_payment_day']];

                        $paymentDate->modify('this '.$dayName);

                        if ($paymentDate->greaterThan($billingPeriodStart)) {
                            $paymentDate->subWeek();
                        }
                    }
                } else {
                    // 获取指定的星期名称
                    $dayName = $daysOfWeek[$input['sc_payment_day']];

                    $paymentDate->modify('this '.$dayName);

                    // 后付方式：付款日期应晚于账单周期结束日期
                    if ($paymentDate->lessThanOrEqualTo($billingPeriodEnd)) {
                        // 如果付款日期早于或等于账单周期结束日期，月份加一
                        $paymentDate->addWeek();
                    }
                }
            } else {
                // 根据付款方式调整付款日期
                if ($prepaid) {
                    if (1 !== $installmentNumber) {
                        // 将付款日转换为整数
                        $paymentDay = (int) $input['sc_payment_day'];
                        // 设置付款日期的天数部分
                        $paymentDate->day($paymentDay);

                        // 预付方式：付款日期应不早于账单周期开始日期
                        if ($paymentDate->greaterThan($billingPeriodStart)) {
                            // 如果付款日期晚于账单周期开始日期，月份减一
                            $paymentDate->subMonthNoOverflow();
                        }
                    }
                } else {
                    // 将付款日转换为整数
                    $paymentDay = (int) $input['sc_payment_day'];
                    // 设置付款日期的天数部分
                    $paymentDate->day($paymentDay);

                    // 后付方式：付款日期应晚于账单周期结束日期
                    if ($paymentDate->lessThanOrEqualTo($billingPeriodEnd)) {
                        // 如果付款日期早于或等于账单周期结束日期，月份加一
                        $paymentDate->addMonthNoOverflow();
                    }
                }
            }

            // 计算账单周期天数
            $days = $billingPeriodStart->diffInDays($billingPeriodEnd, true) + 1;

            // 创建备注信息
            $beginDateText = 1 === $installmentNumber ? '白天' : '0点';
            $endDateText   = $installmentNumber === (int) $input['sc_installments'] ? '白天' : '24点';
            $remark        = sprintf('第%d期租金(%d天,账单周期：%s %s ~ %s %s)', $installmentNumber, $days, $billingPeriodStart->toDateString(), $beginDateText, $billingPeriodEnd->toDateString(), $endDateText);

            // 添加租金付款
            $payment = new Payment([
                'p_pt_id' => PPtId::RENT, 'p_should_pay_date' => $paymentDate->toDateString(), 'p_should_pay_amount' => $input['sc_rent_amount'], 'p_remark' => $remark,
            ]);
            $payment->p_period = ['start_d' => $billingPeriodStart->toDateString(), 'end_d' => $billingPeriodEnd->toDateString()];

            $schedule->push($payment);

            // 检查是否达到总期数
            ++$installmentNumber;
            if ($installmentNumber > (int) $input['sc_installments']) {
                break;
            }

            // 准备下一期
            $currentDate = $billingPeriodEnd->clone()->addDay();
        }

        $schedule->load('PaymentType');

        return $this->response($request)->withData($schedule->toArray())->respond();
    }

    public static function callPaymentsOption($input)
    {
        $subRequest = Request::create(
            '',
            'GET',
            $input,
            server: [                        // server(含请求头)
                'CONTENT_TYPE' => 'application/json',   // 注意：没有 HTTP_ 前缀
                'HTTP_ACCEPT'  => 'application/json',   // 头信息要加 HTTP_ 前缀
            ]
        );

        static $SaleContractController = null;

        if (null === $SaleContractController) {
            $SaleContractController = App::make(SaleContractController::class);
        }

        $response = App::call(
            [$SaleContractController, 'paymentsOption'],
            ['request' => $subRequest]
        );

        $payments = $response->original['data'];

        return $payments;
    }

    /**
     * 通过签约模板生成.
     */
    #[PermissionAction(PermissionAction::WRITE)]
    public function generate(Request $request, SaleContractTpl $saleContractTpl): Response
    {
        $saleContractTpl->append('sc_no');

        $items  = $saleContractTpl->toArray();
        $result = [];
        foreach ($items as $item_key => $item_value) {
            if (null === $item_value || '' === $item_value) {
                continue;
            }
            $item_key          = str_replace('sct_', 'sc_', $item_key);
            $result[$item_key] = $item_value;
        }

        return $this->response()->withData($result)->respond();
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
