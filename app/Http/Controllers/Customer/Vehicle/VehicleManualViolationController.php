<?php

namespace App\Http\Controllers\Customer\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\VehicleManualViolation;
use Illuminate\Database\Query\Builder;
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
        $data = VehicleManualViolation::customerQuery($this)
            ->when(
                $request->get('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('vv.vv_id', '<', $request->get('last_id'));
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
