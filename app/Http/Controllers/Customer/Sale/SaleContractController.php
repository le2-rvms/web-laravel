<?php

namespace App\Http\Controllers\Customer\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sale\SaleContract;
use Illuminate\Database\Query\Builder;
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

        $data = SaleContract::indexQuery()
            ->where('cu.cu_id', '=', $auth->id())
            ->where('sc.sc_is_current_version', '=', true)
            ->when(
                $request->query('last_id'),
                function (Builder $query) use ($request) {
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
