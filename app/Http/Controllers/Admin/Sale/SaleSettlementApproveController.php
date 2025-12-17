<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\Payment\SsDeleteOption;
use App\Enum\Sale\SsReturnStatus;
use App\Enum\SaleContract\ScStatus;
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
                if ($validator->failed()) {
                    return;
                }
                if (SsReturnStatus::CONFIRMED === $saleSettlement->ss_return_status) {
                    $validator->errors()->add('ss_return_status', '不能重复审核');
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        //        $input = $validator->validated();

        $saleContract = $saleSettlement->SaleContract;

        $unPayCount = Payment::query()
            ->where('p_sc_id', '=', $saleContract->sc_id)
            ->where('p_is_valid', '=', PIsValid::VALID)
            ->where('p_pay_status', '=', PPayStatus::UNPAID)
            ->where('p_pt_id', '!=', PPtId::VEHICLE_RETURN_SETTLEMENT_FEE)
            ->count()
        ;

        DB::transaction(function () use ($saleContract, $unPayCount, $saleSettlement) {
            $saleContract->update([
                'sc_status'                                                     => $unPayCount > 0 ? ScStatus::EARLY_TERMINATION : ScStatus::COMPLETED,
                $unPayCount > 0 ? 'sc_early_termination_at' : 'sc_completed_at' => now(),
            ]);

            $saleContract->Vehicle->updateStatus(ve_status_rental: VeStatusRental::PENDING);

            switch ($saleSettlement->delete_option) {
                case SsDeleteOption::DELETE:
                    Payment::query()
                        ->where('p_sc_id', '=', $saleContract->sc_id)
                        ->where('p_pay_status', '=', PPayStatus::UNPAID)
                        ->where('p_pt_id', '!=', PPtId::VEHICLE_RETURN_SETTLEMENT_FEE)
                        ->update([
                            'p_is_valid' => PIsValid::INVALID,
                        ])
                    ;

                    break;

                case SsDeleteOption::DO_NOT_DELETE:
                default:
                    break;
            }

            if ($saleSettlement->ss_settlement_amount > 0 || $saleSettlement->ss_deposit_return_amount > 0) {
                Payment::query()->updateOrCreate([
                    'p_sc_id' => $saleContract->sc_id,
                    'p_pt_id' => $saleSettlement->ss_deposit_return_amount > 0 ? PPtId::REFUND_DEPOSIT : PPtId::VEHICLE_RETURN_SETTLEMENT_FEE,
                ], [
                    'p_should_pay_date'   => $saleSettlement->ss_deposit_return_date,
                    'p_should_pay_amount' => bccomp($saleSettlement->ss_deposit_return_amount, '0', 2) > 0 ? '-'.$saleSettlement->ss_deposit_return_amount : $saleSettlement->ss_settlement_amount,
                    'p_remark'            => (function () use ($saleSettlement): string {
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
            //                    'sc_id' => $saleContract->sc_id,
            //                ])->delete();

            $saleSettlement->update([
                'ss_return_status' => SsReturnStatus::CONFIRMED,
                'ss_approved_by'   => Auth::id(),
                'ss_approved_at'   => now(),
            ]);
        });

        return $this->response()->withData($saleSettlement)->withMessages(message_success(__METHOD__))->respond();
    }

    protected function options(?bool $with_group_count = false): void {}
}
