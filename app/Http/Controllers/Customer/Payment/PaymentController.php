<?php

namespace App\Http\Controllers\Customer\Payment;

use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\SaleContract\ScStatus;
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
            PPayStatus::labelOptions(),
        );
    }

    public function index(Request $request): Response
    {
        $this->response()->withExtras(
            PIsValid::options(),
            PaymentType::options(),
        );

        $data = Payment::customerQuery($this)
            ->where('p.p_is_valid', '=', PIsValid::VALID)
            ->whereIn('sc.sc_status', [ScStatus::SIGNED, ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION])
            ->when(
                $request->get('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('p.p_id', '<', $request->get('last_id'));
                }
            )
            ->get()
        ;

        return $this->response()->withData($data)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            PPayStatus::options(),
        );
    }
}
