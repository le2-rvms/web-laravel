<?php

namespace App\Http\Controllers\Customer\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Sale\VehicleTmp;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleReplacementController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    public function index(Request $request): Response
    {
        $data = VehicleTmp::customerQuery($this)
            ->when(
                $request->get('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('vr.vr_id', '<', $request->get('last_id'));
                }
            )
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
