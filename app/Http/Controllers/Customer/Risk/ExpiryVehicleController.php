<?php

namespace App\Http\Controllers\Customer\Risk;

use App\Http\Controllers\Controller;
use App\Models\Risk\ExpiryVehicle;
use App\Models\Sale\SaleContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpiryVehicleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request): Response
    {
        $page = $request->input('page', 1);

        $perPage = 20;

        $this->response()->withExtras(
            ['perPage' => $perPage]
        );

        $data = ExpiryVehicle::indexQuery()
            ->whereIn('ve.ve_id', SaleContract::CustomerHasVeId())
            ->forPage($page, $perPage)
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
