<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Payment\PaStatus;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\Sale\DtExportType;
use App\Enum\Sale\DtStatus;
use App\Enum\Sale\DtType;
use App\Enum\SaleContract\ScStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Payment\PaymentType;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleContract;
use App\Services\DocTplService;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('收付款')]
class PaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            PPayStatus::labelOptions(),
            PIsValid::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query   = Payment::indexQuery();
        $columns = Payment::indexColumns();

        /** @var Admin $admin */
        $admin = auth()->user();

        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('cu.cu_team_id', $admin->a_team_ids)->orWhereNull('cu.cu_team_id');
            });
        }

        $paginate = new PaginateService(
            [],
            [['p.p_sc_id', 'desc'], ['p.p_id', 'asc']],
            ['kw', 'p_pt_id', 'p_pay_status', 'p_is_valid', 'p_should_pay_date', 'p_actual_pay_date'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->whereLike('sc.sc_no', '%'.$value.'%')->orWhereLike('p.p_remark', '%'.$value.'%')->orWhereLike('ve.ve_plate_no', '%'.$value.'%')->orWhereLike('cu.cu_contact_name', '%'.$value.'%')->orWhereLike('cu.cu_contact_phone', '%'.$value.'%');
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request)
    {
        $this->options();
        $this->response()->withExtras(
            SaleContract::options(
                function (Builder $builder) {
                    $builder->whereIn('sc.sc_status', [ScStatus::PENDING, ScStatus::SIGNED]);
                }
            ),
        );

        return $this->edit(null);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request)
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(Payment $payment): Response
    {
        $payment->load(['SaleContract', 'PaymentType', 'SaleContract.Customer']);

        return $this->response()->withData($payment)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function shows(string $id): Response
    {
        $idsArray = explode(',', $id); // 将 $id 按照逗号分割成数组

        $payments = Payment::query()->whereIn('p_id', $idsArray)->with(['SaleContract', 'PaymentType', 'SaleContract.Customer'])->get();

        return $this->response()->withData($payments)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(?Payment $payment): Response
    {
        $this->options();
        $this->response()->withExtras(
            PaymentAccount::options(),
            DocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtType::PAYMENT);
            })
        );

        if ($payment) {
            $payment->p_actual_pay_date = now()->format('Y-m-d');
            $payment->load(['SaleContract', 'PaymentType', 'SaleContract.Customer']);

            // 准备待付款数据
            $payment->payment = new Payment([
                'p_pt_id'           => $payment->p_pt_id,
                'p_payment_type'    => PaymentType::query()->where('pt_id', '=', $payment->p_pt_id)->first(),
                'p_should_pay_date' => now()->format('Y-m-d'),
                'p_pay_status'      => PPayStatus::UNPAID,
            ])->load(['PaymentType']);

            $customer = $payment->SaleContract->Customer;

            $this->response()->withExtras(
                Payment::indexList(function (Builder $query) use ($payment) {
                    //                    $query->where('sc.sc_cu_id', '=', $customer->cu_id);
                    $query->where('sc.sc_id', '=', $payment->p_sc_id);
                    $query->orderBy('p.p_sc_id')->orderby('p.p_should_pay_date')->orderby('p.p_id');
                }),
                Payment::indexStat(),
            );
        }

        return $this->response()->withData($payment)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, Payment $payment, DocTplService $docTplService)
    {
        $input = $request->validate([
            'dt_export_type' => ['required', Rule::in(DtExportType::label_keys())],
            'dt_id'          => ['required', Rule::exists(DocTpl::class, 'dt_id')->where('dt_type', DtType::PAYMENT)->where('dt_status', DtStatus::ENABLED)],
        ]);

        $payment->load(['SaleContract', 'PaymentType', 'SaleContract.Customer']); // toto 名字有变化

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $payment);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function undo(Request $request, Payment $payment): Response
    {
        $input = Validator::make(
            $request->all(),
            [
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($payment) {
                if ($validator->failed()) {
                    return;
                }
                if (PPayStatus::PAID !== $payment->p_pay_status->value) {
                    $validator->errors()->add('p_pay_status', '未支付，无需退还');
                }
            })
            ->validate()
        ;

        DB::transaction(function () use (&$input, $payment) {
            $payment->update([
                'p_pay_status'        => PPayStatus::UNPAID,
                'p_actual_pay_date'   => null,
                'p_actual_pay_amount' => null,
                'p_pa_id'             => null,
            ]);
        });

        return $this->response()->withData($payment)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?Payment $payment): Response
    {
        ['p_pay_status' => $p_pay_status] = $input0 = Validator::make(
            $request->all(),
            [
                'p_pay_status' => ['bail', 'required', Rule::in(PPayStatus::label_keys())],
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        $input = Validator::make(
            $request->all(),
            [
                'p_sc_id'             => ['bail', 'required', 'integer', Rule::exists(SaleContract::class, 'sc_id')],
                'p_pt_id'             => ['bail', 'required', Rule::in(PPtId::label_keys())],
                'p_should_pay_date'   => ['bail', 'required', 'date'],
                'p_should_pay_amount' => ['bail', 'required', 'numeric'],
                'p_remark'            => ['bail', 'nullable', 'string'],
            ]
            + (PPayStatus::PAID === $p_pay_status ? [
                'p_actual_pay_date'   => ['bail', 'required', 'date'],
                'p_actual_pay_amount' => ['bail', 'required', 'numeric'],
                'p_pa_id'             => ['bail', 'required', Rule::exists(PaymentAccount::class, 'pa_id')->where('pa_status', PaStatus::ENABLED)],
            ] : [])
            + ($payment ? [
                'add_should_pay'              => ['bail', 'nullable', 'boolean'],
                'payment.p_pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in($payment->p_pt_id)],
                'payment.p_should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'payment.p_should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'payment.p_remark'            => ['bail', 'nullable', 'string'],
            ] : []),
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($payment, $request, &$vehicle, &$customer) {
                if ($validator->failed()) {
                    return;
                }
                if (PPayStatus::UNPAID === $request->input('p_pay_status')) {
                    // 未支付时禁止填实际收付信息，避免数据自相矛盾。
                    if ($request->input('p_actual_pay_date') || $request->input('p_actual_pay_amount') || $request->input('p_pa_id')) {
                        $validator->errors()->add('p_pay_status', '支付状态为「未支付」时，实际收付金额、日期、收支账户不允许填入。');

                        return;
                    }
                }

                if ($payment && $payment->exists) {
                    if (PPayStatus::PAID == $payment->p_pay_status->value) {
                        // 已支付记录不能直接改回未支付，应走退回流程。
                        if (PPayStatus::UNPAID === $request->input('p_pay_status')) {
                            $validator->errors()->add('p_pay_status', '「已支付」状态不能改为「未支付」状态，应该使用「退回」');

                            return;
                        }
                    }

                    // 验证： 当修改状态 && 选择已支付的时候， 应收/付金额  - 实际收/付金额 = 少收付金额，少收付金额 > 0 ：验证要有代收付款的信息；代收付款 === true 的时候，金额要等于 少收付金额
                    if (PPayStatus::PAID === $request->input('p_pay_status')) {
                        $less = bcsub($request->input('p_should_pay_amount'), $request->input('p_actual_pay_amount'), 2);
                        if (bccomp($less, '0', 2) > 0) {
                            if (null === $request->input('add_should_pay')) {
                                $validator->errors()->add('add_should_pay', '收付款信息必填。');

                                return;
                            }

                            if (0 !== bccomp($request->input('payment.p_should_pay_amount'), $less, 2)) {
                                $validator->errors()->add('add_should_pay', '收付款信息中的应收/付金额错误。');

                                return;
                            }
                        }
                    }
                }
            })
            ->validate()
        ;

        // 当是修改状态、当选择已支付，当打开了代收款， 计划金额修改为实际金额
        if ($payment && $payment->exists) {
            if (PPayStatus::PAID === $input0['p_pay_status']) {
                if ($input['add_should_pay']) {
                    $input['p_should_pay_amount'] = $input['p_actual_pay_amount'];
                }
            }
        }

        $input += $input0;

        DB::transaction(function () use (&$input, &$payment) {
            if ($payment && $payment->exists) {
                $payment->update($input);

                if ($input_payment = $input['payment']) {
                    $input_payment['p_sc_id']      = $payment->p_sc_id;
                    $input_payment['p_pay_status'] = PPayStatus::UNPAID;
                    $input_payment['p_is_valid']   = PIsValid::VALID;
                    $input_payment['p_remark']     = $input_payment['p_remark'] ?? $input['p_remark'];
                    Payment::query()->create($input_payment);
                }
            } else {
                $payment = Payment::query()->create($input);
            }
        });

        return $this->response()->withData($payment)->respond();
    }

    public function destroy(Payment $payment) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            PaymentType::options_with_count(),
            $with_group_count ? PPayStatus::options_with_count(Payment::class) : PPayStatus::options(),
            $with_group_count ? PIsValid::options_with_count(Payment::class) : PIsValid::options(),
        );
    }
}
