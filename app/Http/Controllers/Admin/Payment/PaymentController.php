<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PaPaStatus;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Sale\DtDtExportType;
use App\Enum\Sale\DtDtStatus;
use App\Enum\Sale\DtDtType;
use App\Enum\Sale\SoOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Payment\PaymentType;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleOrder;
use App\Services\DocTplService;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('收付款管理')]
class PaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            RpPayStatus::labelOptions(),
            RpIsValid::labelOptions(),
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

        $paginate = new PaginateService(
            [],
            [],
            ['kw', 'rp_pt_id', 'rp_pay_status', 'rp_is_valid', 'rp_should_pay_date', 'rp_actual_pay_date'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->whereLike('so.contract_number', '%'.$value.'%')
                            ->orWhereLike('rp.rp_remark', '%'.$value.'%')
                            ->orWhereLike('ve.plate_no', '%'.$value.'%')
                            ->orWhereLike('cu.contact_name', '%'.$value.'%')
                            ->orWhereLike('cu.contact_phone', '%'.$value.'%')
                        ;
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
            SaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::PENDING, SoOrderStatus::SIGNED]);
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
        $payment->load(['SaleOrder', 'PaymentType', 'SaleOrder.Customer']);

        return $this->response()->withData($payment)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function shows(string $id): Response
    {
        $idsArray = explode(',', $id); // 将 $id 按照逗号分割成数组

        $payments = Payment::query()->whereIn('rp_id', $idsArray)->with(['SaleOrder', 'PaymentType', 'SaleOrder.Customer'])->get();

        return $this->response()->withData($payments)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(?Payment $payment): Response
    {
        $this->options();
        $this->response()->withExtras(
            PaymentAccount::options(),
            DocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtDtType::PAYMENT);
            })
        );

        if ($payment) {
            $payment->load(['SaleOrder', 'PaymentType', 'SaleOrder.Customer']);
        }

        return $this->response()->withData($payment)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, Payment $payment, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required',
                Rule::exists(DocTpl::class)->where('dt_type', DtDtType::PAYMENT)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $payment->load(['SaleOrder', 'PaymentType', 'SaleOrder.Customer']); // toto 名字有变化

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $payment);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function undo(Request $request, Payment $payment): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($payment) {
                if (!$validator->failed()) {
                    if (RpPayStatus::PAID !== $payment->pay_status->value) {
                        $validator->errors()->add('pay_status', '未支付，无需退还');
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, $payment) {
            $payment->update([
                'pay_status'        => RpPayStatus::UNPAID,
                'actual_pay_date'   => null,
                'actual_pay_amount' => null,
                'pa_id'             => null,
            ]);
        });

        return $this->response()->withData($payment)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?Payment $payment): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'so_id'             => ['bail', 'required', 'integer', Rule::exists(SaleOrder::class)],
                'pt_id'             => ['bail', 'required', Rule::in(RpPtId::label_keys())],
                'should_pay_date'   => ['bail', 'required', 'date'],
                'should_pay_amount' => ['bail', 'required', 'numeric'],
                'pay_status'        => ['bail', 'required', Rule::in(RpPayStatus::label_keys())],
                'actual_pay_date'   => [
                    'bail',
                    Rule::requiredIf(RpPayStatus::PAID === $request->input('pay_status')),
                    Rule::excludeIf(RpPayStatus::UNPAID === $request->input('pay_status')),
                    'date'],
                'actual_pay_amount' => [
                    'bail',
                    Rule::requiredIf(RpPayStatus::PAID === $request->input('pay_status')),
                    Rule::excludeIf(RpPayStatus::UNPAID === $request->input('pay_status')),
                    'numeric',
                ],
                'pa_id' => [
                    'bail',
                    Rule::requiredIf(RpPayStatus::PAID === $request->input('pay_status')),
                    Rule::excludeIf(RpPayStatus::UNPAID === $request->input('pay_status')),
                    Rule::exists(PaymentAccount::class)->where('pa_status', PaPaStatus::ENABLED),
                ],
                'rp_remark' => ['bail', 'nullable', 'string'],
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($payment, $request, &$vehicle, &$customer) {
                if (!$validator->failed()) {
                    if (RpPayStatus::UNPAID === $request->input('pay_status')) {
                        if ($request->input('actual_pay_date') || $request->input('actual_pay_amount') || $request->input('pa_id')) {
                            $validator->errors()->add('pay_status', '支付状态为「未支付」时，实际收付金额、日期、收支账户不允许填入。');
                        }
                    }

                    if ($payment && $payment->exists) {
                        if (RpPayStatus::PAID == $payment->pay_status->value) {
                            if (RpPayStatus::UNPAID === $request->input('pay_status')) {
                                $validator->errors()->add('pay_status', '「已支付」状态不能改为「未支付」状态，应该使用「退回」');
                            }
                        }
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$payment) {
            if ($payment && $payment->exists) {
                $payment->update($input);
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
            $with_group_count ? RpPayStatus::options_with_count(Payment::class) : RpPayStatus::options(),
            $with_group_count ? RpIsValid::options_with_count(Payment::class) : RpIsValid::options(),
        );
    }
}
