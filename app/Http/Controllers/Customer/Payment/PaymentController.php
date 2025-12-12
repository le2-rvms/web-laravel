<?php

namespace App\Http\Controllers\Customer\Payment;

use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Sale\ScScStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentType;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            RpPayStatus::labelOptions(),
        );
    }

    public function index(Request $request): Response
    {
        $this->response()->withExtras(
            RpIsValid::options(),
            PaymentType::options(),
        );

        $data = Payment::customerQuery($this)
            ->where('rp.is_valid', '=', RpIsValid::VALID)
            ->whereIn('sc.sc_status', [ScScStatus::SIGNED, ScScStatus::COMPLETED, ScScStatus::EARLY_TERMINATION])
            ->when(
                $request->get('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('rp.rp_id', '<', $request->get('last_id'));
                }
            )
            ->get()
        ;

        return $this->response()->withData($data)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            RpPayStatus::options(),
        );
    }
}
