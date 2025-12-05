<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Payment\RsDeleteOption;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SsReturnStatus;
use App\Enum\Vehicle\VeStatusRental;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Sale\SaleSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('退车结算审核')]
class SaleSettlementApproveController extends Controller
{
    public static function labelOptions(Controller $controller): void {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, SaleSettlement $saleSettlement): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
            ],
            [],
            trans_property(Payment::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($saleSettlement) {
                if (!$validator->failed()) {
                    if (SsReturnStatus::CONFIRMED === $saleSettlement->return_status) {
                        $validator->errors()->add('return_status', '不能重复审核');
                    }
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        //        $input = $validator->validated();

        $saleOrder = $saleSettlement->SaleOrder;

        $unPayCount = Payment::query()
            ->where('so_id', '=', $saleOrder->so_id)
            ->where('is_valid', '=', RpIsValid::VALID)
            ->where('pay_status', '=', RpPayStatus::UNPAID)
            ->where('pt_id', '!=', RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE)
            ->count()
        ;

        DB::transaction(function () use ($saleOrder, $unPayCount, $saleSettlement) {
            $saleOrder->update([
                'order_status'                                            => $unPayCount > 0 ? SoOrderStatus::EARLY_TERMINATION : SoOrderStatus::COMPLETED,
                $unPayCount > 0 ? 'early_termination_at' : 'completed_at' => now(),
            ]);

            $saleOrder->Vehicle->updateStatus(status_rental: VeStatusRental::PENDING);

            switch ($saleSettlement->delete_option) {
                case RsDeleteOption::DELETE:
                    Payment::query()
                        ->where('so_id', '=', $saleOrder->so_id)
                        ->where('pay_status', '=', RpPayStatus::UNPAID)
                        ->where('pt_id', '!=', RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE)
                        ->update([
                            'is_valid' => RpIsValid::INVALID,
                        ])
                    ;

                    break;

                case RsDeleteOption::DO_NOT_DELETE:
                default:
                    break;
            }

            if ($saleSettlement->settlement_amount > 0 || $saleSettlement->deposit_return_amount > 0) {
                Payment::query()->updateOrCreate([
                    'so_id' => $saleOrder->so_id,
                    'pt_id' => $saleSettlement->deposit_return_amount > 0 ? RpPtId::REFUND_DEPOSIT : RpPtId::VEHICLE_RETURN_SETTLEMENT_FEE,
                ], [
                    'should_pay_date'   => $saleSettlement->deposit_return_date,
                    'should_pay_amount' => bccomp($saleSettlement->deposit_return_amount, '0', 2) > 0 ? '-'.$saleSettlement->deposit_return_amount : $saleSettlement->settlement_amount,
                    'ss_remark'         => (function () use ($saleSettlement): string {
                        $remark_array = array_combine(
                            array_intersect_key(trans_property(SaleSettlement::class), array_flip(array_keys(SaleSettlement::calcOpts))),
                            array_intersect_key($saleSettlement->toArray(), array_flip(array_keys(SaleSettlement::calcOpts)))
                        );
                        $remark_array = array_filter($remark_array, fn ($v) => 0.0 != floatval($v));

                        return implode(';', array_map(fn ($key, $value) => "{$key}:{$value}", array_keys($remark_array), $remark_array));
                    })(),
                ]);
            }
            //                Payment::query()->where([
            //                    'so_id' => $saleOrder->so_id,
            //                ])->delete();

            $saleSettlement->update([
                'return_status' => SsReturnStatus::CONFIRMED,
                'approved_by'   => Auth::id(),
                'approved_at'   => now(),
            ]);
        });

        return $this->response()->withData($saleSettlement)->withMessages(message_success(__METHOD__))->respond();
    }

    protected function options(?bool $with_group_count = false): void {}
}
