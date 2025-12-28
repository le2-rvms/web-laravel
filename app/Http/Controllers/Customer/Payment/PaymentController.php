<?php

namespace App\Http\Controllers\Customer\Payment;

use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PtIsActive;
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
        $perPage = 20;

        $this->response()->withExtras(
            ['perPage' => $perPage],
            PIsValid::options(),
            PaymentType::options(function ($builder) {
                // 仅展示启用的收付款类型。
                $builder->where('pt_is_active', PtIsActive::ENABLED);
            }),
        );

        $data = Payment::indexQuery()
            // 仅展示有效的收付记录与特定合同状态。
            ->where('p.p_is_valid', '=', PIsValid::VALID)
            ->whereIn('sc.sc_status', [ScStatus::SIGNED, ScStatus::COMPLETED, ScStatus::EARLY_TERMINATION])
            ->where('sc.sc_cu_id', '=', auth()->id())
            ->when(
                $request->query('last_id'),
                function (Builder $query) use ($request) {
                    // 基于 last_id 的游标分页。
                    $query->where('p.p_id', '<', $request->query('last_id'));
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
