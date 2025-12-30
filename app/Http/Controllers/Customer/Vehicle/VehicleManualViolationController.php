<?php

namespace App\Http\Controllers\Customer\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\VehicleManualViolation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleManualViolationController extends Controller
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

        // 按当前客户筛选手动违章记录，使用游标分页。
        $data = VehicleManualViolation::indexQuery()
            // 仅查询当前客户的手动违章记录。
            ->where('sc.sc_cu_id', '=', $auth->id())
            ->when(
                $request->query('last_id'),
                function (Builder $query) use ($request) {
                    // 基于 last_id 的游标分页。
                    $query->where('vv.vv_id', '<', $request->query('last_id'));
                }
            )
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
