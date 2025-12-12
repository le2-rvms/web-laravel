<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\AdmTeamLimit;
use App\Enum\Payment\RpPtId;
use App\Enum\Sale\DtDtExportType;
use App\Enum\Sale\DtDtStatus;
use App\Enum\Sale\DtDtType;
use App\Enum\Sale\ScPaymentDay_Month;
use App\Enum\Sale\ScPaymentDay_Week;
use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\ScRentalType_Short;
use App\Enum\Sale\ScScStatus;
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
use App\Models\Sale\VehicleReplacement;
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
            ScPaymentDayType::labelOptions(),
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
        if (($admin->team_limit->value ?? null) === AdmTeamLimit::LIMITED && $admin->team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('cu.cu_team_id', $admin->team_ids)->orWhereNull('cu.cu_team_id');
            });
        }

        // 如果是管理员或经理，则可以看到所有的用户；如果不是管理员或经理，则只能看到销售或驾管为自己的用户。
        $role_sales_manager = $admin->hasRole(AdminRole::role_sales);
        if ($role_sales_manager) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereNull('cu.sales_manager')->orWhere('cu.sales_manager', '=', $admin->id);
            });
        }

        $paginate = new PaginateService(
            [],
            [['sc.sc_id', 'desc']],
            ['kw', 'sc_sc_status', 'sc_ve_id', 'sc_cu_id', 'sc_rental_start', 'sc_rental_type'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('sc.contract_number', 'like', '%'.$value.'%')
                            ->orWhere('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('cu.contact_name', 'like', '%'.$value.'%')
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
            'rental_type'  => ScRentalType::LONG_TERM,
            'rental_start' => date('Y-m-d'),
        ]);

        /** @var Admin $admin */
        $admin = auth()->user();

        $this->options();
        $this->response()->withExtras(
            SaleContractTpl::options(),
            Vehicle::options(
                where: function (Builder $builder) use ($admin) {
                    $builder
                        ->whereIn('status_rental', [VeStatusRental::LISTED])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                        ->when(
                            ($admin->team_limit->value ?? null) === AdmTeamLimit::LIMITED && $admin->team_ids,
                            function ($query) use ($admin) {
                                $query->whereIn('ve.ve_team_id', $admin->team_ids)->orwhereNull('ve.ve_team_id');
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
            VehicleReplacement::kvList(sc_id: $saleContract->sc_id),
            VehicleInspection::kvList(sc_id: $saleContract->sc_id),
            Payment::kvList(sc_id: $saleContract->sc_id),
            Payment::kvStat(),
            SaleSettlement::kvList(sc_id: $saleContract->sc_id),
            VehicleUsage::kvList(sc_id: $saleContract->sc_id),
            VehicleRepair::kvList(sc_id: $saleContract->sc_id),
            VehicleRepair::kvStat(),
            VehicleViolation::kvList(sc_id: $saleContract->sc_id),
            VehicleManualViolation::kvList(sc_id: $saleContract->sc_id),
        );

        return $this->response()->withData($saleContract)->respond();
    }

    public function edit(SaleContract $saleContract): Response
    {
        $saleContract->load('Customer', 'Vehicle', 'Payments');

        $this->options();

        $this->response()->withExtras(
            SaleContractTpl::options(),
            Vehicle::options(
                where: function (Builder $builder) use ($saleContract) {
                    $builder->where(function (Builder $query) {
                        $query->whereIn('status_rental', [VeStatusRental::LISTED])
                            ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                        ;
                    })->when(VeStatusRental::PENDING === $saleContract->sc_status->value, function (Builder $query) use ($saleContract) {
                        $query->orWhere('ve.ve_id', '=', $saleContract->ve_id); // 显示出原车辆
                    });
                }
            ),
            Customer::options(),
        );

        $this->response()->withExtras(
            VehicleReplacement::kvList(sc_id: $saleContract->sc_id),
            VehicleInspection::kvList(sc_id: $saleContract->sc_id),
            Payment::kvList(sc_id: $saleContract->sc_id),
            Payment::kvStat(),
            SaleSettlement::kvList(sc_id: $saleContract->sc_id),
            VehicleUsage::kvList(sc_id: $saleContract->sc_id),
            VehicleRepair::kvList(sc_id: $saleContract->sc_id),
            VehicleRepair::kvStat(),
            VehicleViolation::kvList(sc_id: $saleContract->sc_id),
            VehicleViolation::kvStat(),
            VehicleManualViolation::kvList(sc_id: $saleContract->sc_id),
            VehicleManualViolation::kvStat(),
            DocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtDtType::SALE_CONTRACT);
            }),
        );

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, SaleContract $saleContract, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(DocTpl::class)->where('dt_type', DtDtType::SALE_CONTRACT)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $saleContract->load('Customer', 'Vehicle', 'Vehicle.VehicleInsurances', 'Company', 'Payments'); // 'Company',

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $saleContract);

        return $this->response()->withData($url)->respond();
    }

    public function update(Request $request, ?SaleContract $saleContract): Response
    {
        $input1 = $request->validate(
            [
                'rental_type' => ['bail', 'required', Rule::in(ScRentalType::label_keys())],
            ],
            [],
            trans_property(SaleContract::class)
        );
        $rental_type = $input1['rental_type'];

        $is_long_term  = ScRentalType::LONG_TERM === $rental_type;
        $is_short_term = ScRentalType::SHORT_TERM === $rental_type;

        $input2 = $request->validate(
            [
                'payment_day_type' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'string', Rule::in(ScPaymentDayType::label_keys())],
            ],
            [],
            trans_property(SaleContract::class)
        );

        $payment_day_type = $input2['payment_day_type'] ?? null;

        $validator = Validator::make(
            $request->all(),
            [
                'cu_id'           => ['bail', 'required', 'integer'],
                've_id'           => ['bail', 'required', 'integer'],
                'contract_number' => ['bail', 'required', 'string', 'max:50', Rule::unique(SaleContract::class, 'contract_number')->ignore($saleContract)],
                'free_days'       => ['bail', 'nullable', 'int:4'],
                'rental_start'    => ['bail', 'required', 'date', 'before_or_equal:rental_end'],
                'installments'    => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', 'min:1'],
                'rental_days'     => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'nullable', 'int:4'], // 短租的
                'rental_end'      => ['bail', 'required', 'date', 'after_or_equal:rental_start'],

                'deposit_amount'                  => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'rent_amount'                     => ['bail', 'required', 'numeric', 'min:0'],
                'payment_day'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'integer', new PaymentDayCheck($payment_day_type)],
                'total_rent_amount'               => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'insurance_base_fee_amount'       => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'insurance_additional_fee_amount' => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'other_fee_amount'                => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],
                'total_amount'                    => ['bail', Rule::requiredIf($is_short_term), Rule::excludeIf($is_long_term), 'numeric', 'min:0'],

                'cus_1'                        => ['bail', 'nullable', 'max:255'],
                'cus_2'                        => ['bail', 'nullable', 'max:255'],
                'cus_3'                        => ['bail', 'nullable', 'max:255'],
                'discount_plan'                => ['bail', 'nullable', 'max:255'],
                'sc_remark'                    => ['bail', 'nullable', 'max:255'],
                'payments'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'array', 'min:1'],
                'payments.*.pt_id'             => ['bail', 'required', 'integer', Rule::exists(PaymentType::class)],
                'payments.*.should_pay_date'   => ['bail', 'required', 'date'],
                'payments.*.should_pay_amount' => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'payments.*.rp_remark'         => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_object('additional_file'),
            [],
            trans_property(SaleContract::class) + Arr::dot(['payments' => ['*' => trans_property(Payment::class)]])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($is_short_term, $saleContract, $request) {
                if (!$validator->failed()) {
                    // ve_id
                    $ve_id   = $request->input('ve_id');
                    $vehicle = Vehicle::query()->find($ve_id);
                    if (!$vehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::LISTED, VeStatusRental::RESERVED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                    if (!$pass) {
                        return;
                    }

                    // 当车辆变化时候，状态必须是待签约
                    if ($saleContract->ve_id !== $ve_id) {
                        if (ScScStatus::PENDING !== $saleContract->sc_status->value) {
                            $validator->errors()->add('ve_id', '合同在待签约状态下才允许修改车辆。');

                            return;
                        }
                    }

                    $cu_id    = $request->input('cu_id');
                    $customer = Customer::query()->find($cu_id);
                    if (!$customer) {
                        $validator->errors()->add('cu_id', 'The customer does not exist.');

                        return;
                    }

                    if ($saleContract) {
                        if (!$saleContract->check_sc_status([ScScStatus::PENDING], $validator)) {
                            return;
                        }
                    }

                    if ($is_short_term) {
                        if (0 !== bccomp(
                            $request->input('total_rent_amount'),
                            bcmul(
                                $request->input('rent_amount'),
                                bcsub($request->input('rental_days'), $request->input('free_days'), 0),
                                2
                            ),
                            2
                        )) {
                            $validator->errors()->add('total_rent_amount', '总租金计算错误');
                        }

                        if (0 !== bccomp(
                            math_array_bcadd(2, $request->input('deposit_amount'), $request->input('management_fee_amount'), $request->input('total_rent_amount'), $request->input('insurance_base_fee_amount'), $request->input('insurance_additional_fee_amount'), $request->input('other_fee_amount')),
                            $request->input('total_amount'),
                            2
                        )) {
                            $validator->errors()->add('total_amount', '总金额计算错误');
                        }
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $input1 + $input2 + $validator->validated();

        // input数据修正
        if (ScRentalType::LONG_TERM === $input['rental_type']) {
            // 总计租金金额
            $input['total_rent_amount'] = bcmul($input['installments'], $input['rent_amount'], 2);

            // 总计金额
            $input['total_amount'] = math_array_bcadd(2, $input['total_rent_amount'], $input['deposit_amount'], $input['management_fee_amount']);

            // 租期天数 rental_days
            $input['rental_days'] = Carbon::parse($input['rental_start'])->diffInDays(Carbon::parse($input['rental_end']), true) + 1;
        }

        DB::transaction(function () use (&$input, &$saleContract) {
            /** @var SaleContract $saleContract */
            if (null === $saleContract) {
                $saleContract = SaleContract::query()->create($input + ['order_at' => now(), 'sc_status' => ScScStatus::PENDING]);
            } else {
                // 有修改车辆，前提是必须是在初始阶段，要把原车辆的状态改回来。
                if ($saleContract->ve_id !== $input['ve_id']) {
                    $saleContract->Vehicle->updateStatus(status_rental: VeStatusRental::LISTED);
                }

                $saleContract->update($input);
            }

            if (ScRentalType::LONG_TERM === $saleContract->rental_type->value) {
                $saleContract->Payments()->delete();

                foreach ($input['payments'] as $payment) {
                    $saleContract->Payments()->create($payment);
                }
            }

            $saleContract->Vehicle->updateStatus(status_rental: VeStatusRental::RESERVED);
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
        $validator = Validator::make(
            $request->all(),
            [
                'rental_type'           => ['bail', 'required', Rule::in([ScRentalType::LONG_TERM])],
                'payment_day_type'      => ['bail', 'required', 'string', Rule::in(ScPaymentDayType::label_keys())],
                'deposit_amount'        => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'management_fee_amount' => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'rental_start'          => ['bail', 'required', 'date'],
                'installments'          => ['bail', 'required', 'integer', 'min:1'],
                'rent_amount'           => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'payment_day'           => ['bail', 'required', 'integer', new PaymentDayCheck($request->input('payment_day_type'))],
            ],
            [],
            trans_property(SaleContract::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        // 创建数字到星期名称的映射
        $daysOfWeek = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        $paymentType = $input['payment_day_type'];

        list('interval' => $interval, 'interval_unit' => $interval_unit, 'prepaid' => $prepaid) = ScPaymentDayType::interval[$paymentType];

        $paymentDayType = ScPaymentDayType::payment_day_classes[$paymentType];

        $startDate = Carbon::parse($input['rental_start']);

        $free_days = $request->input('free_days');

        $schedule = new Collection();

        // 添加一次性押金
        $schedule->push(new Payment([
            'pt_id'             => RpPtId::DEPOSIT,
            'should_pay_date'   => $startDate->toDateString(),
            'should_pay_amount' => $input['deposit_amount'],
            'rp_remark'         => new RpPtId(RpPtId::DEPOSIT)->label,
        ]));

        if (($management_fee_amount = ($input['management_fee_amount'] ?? null)) && $input['management_fee_amount'] > 0) {// 添加一次性管理费（如果有）
            $schedule->push(new Payment([
                'pt_id'             => RpPtId::MANAGEMENT_FEE,
                'should_pay_date'   => $startDate->toDateString(),
                'should_pay_amount' => $management_fee_amount,
                'rp_remark'         => new RpPtId(RpPtId::MANAGEMENT_FEE)->label,
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
            if (ScPaymentDay_Week::class == $paymentDayType) {
                if ($prepaid) {
                    if (1 !== $installmentNumber) {
                        // 获取指定的星期名称
                        $dayName = $daysOfWeek[$input['payment_day']];

                        $paymentDate->modify('this '.$dayName);

                        if ($paymentDate->greaterThan($billingPeriodStart)) {
                            $paymentDate->subWeek();
                        }
                    }
                } else {
                    // 获取指定的星期名称
                    $dayName = $daysOfWeek[$input['payment_day']];

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
                        $paymentDay = (int) $input['payment_day'];
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
                    $paymentDay = (int) $input['payment_day'];
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
            $endDateText   = $installmentNumber === (int) $input['installments'] ? '白天' : '24点';
            $remark        = sprintf('第%d期租金(%d天,账单周期：%s %s ~ %s %s)', $installmentNumber, $days, $billingPeriodStart->toDateString(), $beginDateText, $billingPeriodEnd->toDateString(), $endDateText);

            // 添加租金付款
            $payment = new Payment([
                'pt_id' => RpPtId::RENT, 'should_pay_date' => $paymentDate->toDateString(), 'should_pay_amount' => $input['rent_amount'], 'rp_remark' => $remark,
            ]);
            $payment->period = ['start_d' => $billingPeriodStart->toDateString(), 'end_d' => $billingPeriodEnd->toDateString()];

            $schedule->push($payment);

            // 检查是否达到总期数
            ++$installmentNumber;
            if ($installmentNumber > (int) $input['installments']) {
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
    #[PermissionAction(PermissionAction::READ)]
    public function generate(Request $request, SaleContractTpl $saleContractTpl): Response
    {
        $saleContractTpl->append('contract_number');

        $result = array_filter($saleContractTpl->toArray());

        return $this->response()->withData($result)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'sale_contract',
            [
                'additional_photos',
                'additional_file',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            ScRentalType::options(),
            $with_group_count ? ScRentalType_Short::options_with_count(SaleContract::class) : ScRentalType_Short::options(),
            ScPaymentDayType::options(),
            ScPaymentDay_Month::options(),
            ScPaymentDay_Week::options(),
            $with_group_count ? ScScStatus::options_with_count(SaleContract::class) : ScScStatus::options(),
        );
    }
}
