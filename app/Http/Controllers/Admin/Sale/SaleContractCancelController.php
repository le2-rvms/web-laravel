<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PIsValid;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('取消租车合同')]
class SaleContractCancelController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, SaleContract $saleContract): Response
    {
        $input = Validator::make(
            $request->all(),
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($saleContract) {
                if (!$saleContract->check_status([ScStatus::PENDING, ScStatus::SIGNED], $validator)) {
                    return;
                }

                if (!$vehicle = $saleContract->Vehicle) {
                    $validator->errors()->add('sc_ve_id', 'The vehicle does not exist.');

                    return;
                }

                if (!$vehicle->check_status(VeStatusService::YES, [VeStatusRental::RESERVED, VeStatusRental::RENTED], [VeStatusDispatch::NOT_DISPATCHED], $validator)) {
                    return;
                }
            })
            ->validate()
        ;

        DB::transaction(function () use (&$saleContract) {
            $saleContract = $saleContract->newQuery()->useWritePdo()
                ->whereKey($saleContract->getKey())->lockForUpdate()->firstOrFail()
            ;

            match ($saleContract->sc_status->value) {
                ScStatus::PENDING => (function () use ($saleContract) {
                    $saleContract->Vehicle->updateStatus(
                        ve_status_rental: VeStatusRental::LISTED,
                    );

                    $saleContract->Payments()->update([
                        'p_is_valid' => PIsValid::INVALID,
                    ]);
                })(),
                ScStatus::SIGNED => (function () use ($saleContract) {
                    $saleContract->Vehicle->updateStatus(
                        ve_status_rental: VeStatusRental::PENDING,
                    );

                    $saleContract->Payments->each(function ($item, $key) {
                        $item->update([
                            'p_is_valid' => PIsValid::INVALID,
                        ]);
                    });
                })(),
            };

            $saleContract->sc_status      = ScStatus::CANCELLED;
            $saleContract->sc_canceled_at = now();
            $saleContract->save();
        });

        $saleContract->refresh();

        return $this->response()->withData($saleContract)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
