<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Console\Commands\Sys\AdminRoleImport;
use App\Enum\Payment\RpPtId;
use App\Enum\Sale\DtDtExportType;
use App\Enum\Sale\DtDtStatus;
use App\Enum\Sale\DtDtType;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SoPaymentDay_Month;
use App\Enum\Sale\SoPaymentDay_Week;
use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType;
use App\Enum\Sale\SoRentalType_Short;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleOrderTpl;
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

#[PermissionType('订单')]
class SaleOrderController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            SoRentalType::labelOptions(),
            SoRentalType_Short::labelOptions(),
            SoPaymentDayType::labelOptions(),
            SoPaymentDay_Month::labelOptions(),
            SoPaymentDay_Week::labelOptions(),
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

        $query   = SaleOrder::indexQuery();
        $columns = SaleOrder::indexColumns();

        // 如果是管理员或经理，则可以看到所有的用户；如果不是管理员或经理，则只能看到销售或驾管为自己的用户。
        $user = auth()->user();

        $role_sales_manager = $user->hasRole(AdminRoleImport::role_sales);
        if ($role_sales_manager) {
            $query->whereNull('cu.sales_manager')->orWhere('cu.sales_manager', '=', $user->id);
        }

        $paginate = new PaginateService(
            [],
            [['so.so_id', 'desc']],
            ['kw', 'so_order_status', 'so_ve_id', 'so_cu_id', 'so_rental_start', 'so_rental_type'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('so.contract_number', 'like', '%'.$value.'%')
                            ->orWhere('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('cu.contact_name', 'like', '%'.$value.'%')
                            ->orWhere('so.so_remark', 'like', '%'.$value.'%')
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
        $saleOrder = new SaleOrder([
            'rental_type'  => SoRentalType::LONG_TERM,
            'rental_start' => date('Y-m-d'),
        ]);

        $this->options();
        $this->response()->withExtras(
            SaleOrderTpl::options(),
            Vehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::LISTED])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
            Customer::options(),
        );

        return $this->response()->withData($saleOrder)->respond();
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
    public function show(SaleOrder $saleOrder): Response
    {
        $saleOrder->load('Customer', 'Vehicle', 'Payments');

        $this->response()->withExtras(
            VehicleReplacement::kvList(so_id: $saleOrder->so_id),
            VehicleInspection::kvList(so_id: $saleOrder->so_id),
            Payment::kvList(so_id: $saleOrder->so_id),
            Payment::kvStat(),
            SaleSettlement::kvList(so_id: $saleOrder->so_id),
            VehicleUsage::kvList(so_id: $saleOrder->so_id),
            VehicleRepair::kvList(so_id: $saleOrder->so_id),
            VehicleRepair::kvStat(),
            VehicleViolation::kvList(so_id: $saleOrder->so_id),
            VehicleManualViolation::kvList(so_id: $saleOrder->so_id),
        );

        return $this->response()->withData($saleOrder)->respond();
    }

    public function edit(SaleOrder $saleOrder): Response
    {
        $saleOrder->load('Customer', 'Vehicle', 'Payments');

        $this->options();

        $this->response()->withExtras(
            SaleOrderTpl::options(),
            Vehicle::options(
                where: function (Builder $builder) use ($saleOrder) {
                    $builder->where(function (Builder $query) {
                        $query->whereIn('status_rental', [VeStatusRental::LISTED])
                            ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                        ;
                    })->when(VeStatusRental::PENDING === $saleOrder->order_status->value, function (Builder $query) use ($saleOrder) {
                        $query->orWhere('ve.ve_id', '=', $saleOrder->ve_id); // 显示出原车辆
                    });
                }
            ),
            Customer::options(),
        );

        $this->response()->withExtras(
            VehicleReplacement::kvList(so_id: $saleOrder->so_id),
            VehicleInspection::kvList(so_id: $saleOrder->so_id),
            Payment::kvList(so_id: $saleOrder->so_id),
            Payment::kvStat(),
            SaleSettlement::kvList(so_id: $saleOrder->so_id),
            VehicleUsage::kvList(so_id: $saleOrder->so_id),
            VehicleRepair::kvList(so_id: $saleOrder->so_id),
            VehicleRepair::kvStat(),
            VehicleViolation::kvList(so_id: $saleOrder->so_id),
            VehicleViolation::kvStat(),
            VehicleManualViolation::kvList(so_id: $saleOrder->so_id),
            VehicleManualViolation::kvStat(),
            DocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtDtType::SALE_ORDER);
            }),
        );

        return $this->response()->withData($saleOrder)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, SaleOrder $saleOrder, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(DocTpl::class)->where('dt_type', DtDtType::SALE_ORDER)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $saleOrder->load('Customer', 'Vehicle', 'Vehicle.VehicleInsurances', 'Company', 'Payments'); // 'Company',

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $saleOrder);

        return $this->response()->withData($url)->respond();
    }

    public function update(Request $request, ?SaleOrder $saleOrder): Response
    {
        $input1 = $request->validate(
            [
                'rental_type' => ['bail', 'required', Rule::in(SoRentalType::label_keys())],
            ],
            [],
            trans_property(SaleOrder::class)
        );
        $rental_type = $input1['rental_type'];

        $is_long_term  = SoRentalType::LONG_TERM === $rental_type;
        $is_short_term = SoRentalType::SHORT_TERM === $rental_type;

        $input2 = $request->validate(
            [
                'payment_day_type' => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'string', Rule::in(SoPaymentDayType::label_keys())],
            ],
            [],
            trans_property(SaleOrder::class)
        );

        $payment_day_type = $input2['payment_day_type'] ?? null;

        $validator = Validator::make(
            $request->all(),
            [
                'cu_id'           => ['bail', 'required', 'integer'],
                've_id'           => ['bail', 'required', 'integer'],
                'contract_number' => ['bail', 'required', 'string', 'max:50', Rule::unique(SaleOrder::class, 'contract_number')->ignore($saleOrder)],
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
                'so_remark'                    => ['bail', 'nullable', 'max:255'],
                'payments'                     => ['bail', Rule::requiredIf($is_long_term), Rule::excludeIf($is_short_term), 'array', 'min:1'],
                'payments.*.pt_id'             => ['bail', 'required', 'integer', Rule::exists(PaymentType::class)],
                'payments.*.should_pay_date'   => ['bail', 'required', 'date'],
                'payments.*.should_pay_amount' => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'payments.*.rp_remark'         => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_object('additional_file'),
            [],
            trans_property(SaleOrder::class) + Arr::dot(['payments' => ['*' => trans_property(Payment::class)]])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($is_short_term, $saleOrder, $request) {
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
                    if ($saleOrder->ve_id !== $ve_id) {
                        if (SoOrderStatus::PENDING !== $saleOrder->order_status->value) {
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

                    if ($saleOrder) {
                        if (!$saleOrder->check_order_status([SoOrderStatus::PENDING], $validator)) {
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
        if (SoRentalType::LONG_TERM === $input['rental_type']) {
            // 总计租金金额
            $input['total_rent_amount'] = bcmul($input['installments'], $input['rent_amount'], 2);

            // 总计金额
            $input['total_amount'] = math_array_bcadd(2, $input['total_rent_amount'], $input['deposit_amount'], $input['management_fee_amount']);

            // 租期天数 rental_days
            $input['rental_days'] = Carbon::parse($input['rental_start'])->diffInDays(Carbon::parse($input['rental_end']), true) + 1;
        }

        DB::transaction(function () use (&$input, &$saleOrder) {
            /** @var SaleOrder $saleOrder */
            if (null === $saleOrder) {
                $saleOrder = SaleOrder::query()->create($input + ['order_at' => now(), 'order_status' => SoOrderStatus::PENDING]);
            } else {
                // 有修改车辆，前提是必须是在初始阶段，要把原车辆的状态改回来。
                if ($saleOrder->ve_id !== $input['ve_id']) {
                    $saleOrder->Vehicle->updateStatus(status_rental: VeStatusRental::LISTED);
                }

                $saleOrder->update($input);
            }

            if (SoRentalType::LONG_TERM === $saleOrder->rental_type->value) {
                $saleOrder->Payments()->delete();

                foreach ($input['payments'] as $payment) {
                    $saleOrder->Payments()->create($payment);
                }
            }

            $saleOrder->Vehicle->updateStatus(status_rental: VeStatusRental::RESERVED);
        });

        $saleOrder->refresh()->load('Payments');

        return $this->response()->withData($saleOrder)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(SaleOrder $saleOrder): Response
    {
        $saleOrder->delete();

        return $this->response()->withData($saleOrder)->respond();
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
                'rental_type'           => ['bail', 'required', Rule::in([SoRentalType::LONG_TERM])],
                'payment_day_type'      => ['bail', 'required', 'string', Rule::in(SoPaymentDayType::label_keys())],
                'deposit_amount'        => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'management_fee_amount' => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'rental_start'          => ['bail', 'required', 'date'],
                'installments'          => ['bail', 'required', 'integer', 'min:1'],
                'rent_amount'           => ['bail', 'required', 'decimal:0,2', 'gte:0'],
                'payment_day'           => ['bail', 'required', 'integer', new PaymentDayCheck($request->input('payment_day_type'))],
            ],
            [],
            trans_property(SaleOrder::class)
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        // 创建数字到星期名称的映射
        $daysOfWeek = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

        $paymentType = $input['payment_day_type'];

        list('interval' => $interval, 'interval_unit' => $interval_unit, 'prepaid' => $prepaid) = SoPaymentDayType::interval[$paymentType];

        $paymentDayType = SoPaymentDayType::payment_day_classes[$paymentType];

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
            if (SoPaymentDay_Week::class == $paymentDayType) {
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
            $remark        = sprintf('第%d期租金（%d天，账单周期：%s %s ~ %s %s）', $installmentNumber, $days, $billingPeriodStart->toDateString(), $beginDateText, $billingPeriodEnd->toDateString(), $endDateText);

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

        static $SaleOrderController = null;

        if (null === $SaleOrderController) {
            $SaleOrderController = App::make(SaleOrderController::class);
        }

        $response = App::call(
            [$SaleOrderController, 'paymentsOption'],
            ['request' => $subRequest]
        );

        $payments = $response->original['data'];

        return $payments;
    }

    /**
     * 通过签约模板生成.
     */
    #[PermissionAction(PermissionAction::READ)]
    public function generate(Request $request, SaleOrderTpl $saleOrderTpl): Response
    {
        $saleOrderTpl->append('contract_number');

        $result = array_filter($saleOrderTpl->toArray());

        return $this->response()->withData($result)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'sale_order',
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
            SoRentalType::options(),
            $with_group_count ? SoRentalType_Short::options_with_count(SaleOrder::class) : SoRentalType_Short::options(),
            SoPaymentDayType::options(),
            SoPaymentDay_Month::options(),
            SoPaymentDay_Week::options(),
            $with_group_count ? SoOrderStatus::options_with_count(SaleOrder::class) : SoOrderStatus::options(),
        );
    }
}
