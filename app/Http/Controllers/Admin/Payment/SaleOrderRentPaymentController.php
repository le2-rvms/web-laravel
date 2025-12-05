<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PaPaStatus;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Sale\SoOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\SaleOrder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('收租金')]
class SaleOrderRentPaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index() {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(string $so_id): Response
    {
        abort_if(!is_numeric($so_id), 404);

        $this->response()->withExtras(
            PaymentAccount::options(),
            RpPayStatus::options(),
            SaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::SIGNED]);
                }
            ),
        );

        if ($so_id > 0) {
            $saleOrder = SaleOrder::query()
                ->where('order_status', '=', SoOrderStatus::SIGNED)
                ->findOrFail($so_id)
            ;
            $saleOrder->load('Customer', 'Vehicle', 'UnpaidRentPayments');

            $this->response()->withExtras(Payment::option($saleOrder->UnpaidRentPayments));
        } else {
            $saleOrder = [];
        }

        return $this->response()->withData($saleOrder)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request, SaleOrder $saleOrder): Response
    {
        $selectedData = $request->input('unpaid_rent_rental_payments')[$request->input('selectedIndex')] ?? null;

        abort_if(!$selectedData, 404);

        $validator = Validator::make(
            $selectedData,
            [
                'rp_id'             => ['bail', 'required', 'int'],
                'pay_status'        => ['bail', 'required', Rule::in([RpPayStatus::PAID])],
                'actual_pay_date'   => ['bail', 'required', 'date'],
                'actual_pay_amount' => ['bail', 'required', 'numeric'],
                'pa_id'             => ['bail', 'required', Rule::exists(PaymentAccount::class)->where('pa_status', PaPaStatus::ENABLED)],
                'rp_remark'         => ['bail', 'nullable', 'string'],
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($saleOrder, &$vehicle, &$customer) {
                if (!$validator->failed()) {
                    if (!$saleOrder->check_order_status([SoOrderStatus::SIGNED], $validator)) {
                        return;
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input) {
            $Payment = Payment::query()->where('rp_id', $input['rp_id'])->lockForUpdate()->first();
            $Payment->update($input);
        });

        return $this->response()->withData($input)->respond();
    }

    public function show(Payment $payment) {}

    public function edit(Payment $payment) {}

    public function update(Request $request, SaleOrder $saleOrder) {}

    public function destroy(Payment $payment) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
