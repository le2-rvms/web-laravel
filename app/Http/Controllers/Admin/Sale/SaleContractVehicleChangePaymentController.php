<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PPayStatus;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusRental;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('合同换车缴费')]
class SaleContractVehicleChangePaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        /** @var null|SaleContract $saleContract */
        $saleContract = null;

        $validator = Validator::make(
            $request->all(),
            [
                'sc_id'      => ['bail', 'required', 'integer', Rule::exists(SaleContract::class, 'sc_id')],
                'from_sc_id' => ['bail', 'required', 'integer', Rule::exists(SaleContract::class, 'sc_id')],
            ],
            [],
            trans_property(SaleContract::class)
        );

        $sourceContract = null;

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$saleContract, &$sourceContract) {
            if ($validator->failed()) {
                return;
            }

            $saleContract   = SaleContract::query()->find($request->input('sc_id'));
            $sourceContract = SaleContract::query()->find($request->input('from_sc_id'));

            if (!$saleContract || !$sourceContract) {
                $validator->errors()->add('sc_id', '合同不存在。');

                return;
            }

            if (!$saleContract->check_status([ScStatus::SIGNED], $validator)) {
                return;
            }

            if ($saleContract->sc_is_current_version) {
                $validator->errors()->add('sc_id', '当前合同已启用，无需重复生效。');
            }

            if (!$sourceContract->check_status([ScStatus::SIGNED], $validator)) {
                return;
            }

            if (!$sourceContract->sc_is_current_version) {
                $validator->errors()->add('from_sc_id', '仅当前版本合同可作为换车来源。');
            }

            if ($saleContract->sc_cu_id !== $sourceContract->sc_cu_id) {
                $validator->errors()->add('sc_id', '新旧合同客户不一致，无法生效。');
            }

            $unpaid = $saleContract->Payments()
                ->where('p_pay_status', '=', PPayStatus::UNPAID)
                ->get()
            ;

            foreach ($unpaid as $payment) {
                if (bccomp($payment->p_should_pay_amount ?? '0', '0', 2) > 0) {
                    $validator->errors()->add('p_pay_status', '存在未支付的换车缴费，请先完成缴费。');

                    break;
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        DB::transaction(function () use ($saleContract, $sourceContract) {
            /** @var SaleContract $source */
            $source = SaleContract::query()->lockForUpdate()->findOrFail($sourceContract->sc_id);

            /** @var SaleContract $target */
            $target = SaleContract::query()->lockForUpdate()->findOrFail($saleContract->sc_id);

            Payment::query()
                ->where('sc_id', '=', $source->sc_id)
                ->where('p_pay_status', '=', PPayStatus::UNPAID)
                ->delete()
            ;

            $source->sc_is_current_version = false;
            $source->save();

            $target->update([
                'sc_is_current_version' => true,
                'sc_version'            => $source->sc_version + 1,
                'signed_at'             => $target->signed_at ?? now(),
            ]);

            $target->Vehicle?->updateStatus(ve_status_rental: VeStatusRental::RENTED);
            $source->Vehicle?->updateStatus(ve_status_rental: VeStatusRental::LISTED);
        });

        $saleContract->refresh()->load('Payments');

        return $this->response()->withData($saleContract)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
