<?php

namespace App\Http\Controllers\Customer\Risk;

use App\Http\Controllers\Controller;
use App\Models\Risk\ExpiryDriver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpiryDriverController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request): Response
    {
        $perPage = 20;

        $this->response()->withExtras(
            ['perPage' => $perPage]
        );

        $auth = auth();

        // 默认统计未来 30 天内证照到期的司机。
        $days = 30;

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        $data = ExpiryDriver::indexQuery()
            ->where('cu.cu_id', '=', $auth->id())
            ->where(function (Builder $q) use ($targetDate) {
                // 驾驶证或身份证任一到期即纳入结果。
                $q->where('cui.cui_driver_license_expiry_date', '<=', $targetDate)->orWhere('cui_id_expiry_date', '<=', $targetDate);
            })
            // 固定取前 N 条结果，避免大查询。
            ->forPage(1, $perPage)
            ->get()
        ;

        return $this->response()->withData($data)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
