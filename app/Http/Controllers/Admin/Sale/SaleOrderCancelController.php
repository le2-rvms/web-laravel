<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('租车订单取消')]
class SaleOrderCancelController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, SaleOrder $saleOrder): Response
    {
        $validator = Validator::make(
            $request->all(),
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($saleOrder) {
                if (!$saleOrder->check_order_status([SoOrderStatus::PENDING, SoOrderStatus::SIGNED], $validator)) {
                    return;
                }

                if (!$vehicle = $saleOrder->Vehicle) {
                    $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                    return;
                }

                if (!$vehicle->check_status(VeStatusService::YES, [VeStatusRental::RESERVED, VeStatusRental::RENTED], [VeStatusDispatch::NOT_DISPATCHED], $validator)) {
                    return;
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$saleOrder) {
            $saleOrder = $saleOrder->newQuery()->useWritePdo()
                ->whereKey($saleOrder->getKey())->lockForUpdate()->firstOrFail()
            ;

            match ($saleOrder->order_status->value) {
                SoOrderStatus::PENDING => (function () use ($saleOrder) {
                    $saleOrder->Vehicle->updateStatus(
                        status_rental: VeStatusRental::LISTED,
                    );

                    $saleOrder->Payments()->update([
                        'is_valid' => RpIsValid::INVALID,
                    ]);
                })(),
                SoOrderStatus::SIGNED => (function () use ($saleOrder) {
                    $saleOrder->Vehicle->updateStatus(
                        status_rental: VeStatusRental::PENDING,
                    );

                    $saleOrder->Payments->each(function ($item, $key) {
                        $item->update([
                            'is_valid' => RpIsValid::INVALID,
                        ]);
                    });
                })(),
            };

            $saleOrder->order_status = SoOrderStatus::CANCELLED;
            $saleOrder->canceled_at  = now();
            $saleOrder->save();
        });

        $saleOrder->refresh();

        return $this->response()->withData($saleOrder)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
