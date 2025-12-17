<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PaStatus;
use App\Enum\Payment\PPayStatus;
use App\Enum\SaleContract\ScStatus;
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

#[PermissionType('收租金')]
class SaleContractRentPaymentController extends Controller
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
                    $builder->whereIn('sc.sc_status', [ScStatus::SIGNED]);
                }
            ),
        );

        if ($sc_id > 0) {
            $saleContract = SaleContract::query()
                ->where('sc_status', '=', ScStatus::SIGNED)
                ->findOrFail($sc_id)
            ;
            $saleContract->load('Customer', 'Vehicle', 'UnpaidRentPayments');

            $this->response()->withExtras(Payment::option($saleContract->UnpaidRentPayments));
        } else {
            $saleContract = [];
        }

        return $this->response()->withData($saleContract)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request, SaleContract $saleContract): Response
    {
        $selectedData = $request->input('unpaid_rent_payments')[$request->input('selectedIndex')] ?? null;

        abort_if(!$selectedData, 404);

        $validator = Validator::make(
            $selectedData,
            [
                'p_id'                => ['bail', 'required', 'int'],
                'p_pay_status'        => ['bail', 'required', Rule::in([PPayStatus::PAID])],
                'p_actual_pay_date'   => ['bail', 'required', 'date'],
                'p_actual_pay_amount' => ['bail', 'required', 'numeric'],
                'p_pa_id'             => ['bail', 'required', Rule::exists(PaymentAccount::class, 'pa_id')->where('pa_status', PaStatus::ENABLED)],
                'p_remark'            => ['bail', 'nullable', 'string'],
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($saleContract, &$vehicle, &$customer) {
                if ($validator->failed()) {
                    return;
                }
                if (!$saleContract->check_status([ScStatus::SIGNED], $validator)) {
                    return;
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input) {
            $Payment = Payment::query()->where('p_id', $input['p_id'])->lockForUpdate()->first();
            $Payment->update($input);
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
