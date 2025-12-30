<?php

namespace App\Http\Controllers\Customer\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SaleContractController extends Controller
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

        // 按当前客户过滤合同，使用游标分页。
        $data = SaleContract::indexQuery()
            // 仅查询当前客户的合同。
            ->where('cu.cu_id', '=', $auth->id())
            ->when(
                $request->query('last_id'),
                function (Builder $query) use ($request) {
                    // 基于 last_id 的游标分页。
                    $query->where('sc.sc_id', '<', $request->query('last_id'));
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
