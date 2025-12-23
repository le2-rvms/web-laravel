<?php

namespace App\Http\Controllers\Customer\Vehicle;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\VehicleMaintenance;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleMaintenanceController extends Controller
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

        $data = VehicleMaintenance::indexQuery()
            ->where('sc.sc_cu_id', '=', $auth->id())
            ->forPage(1, $perPage)
            ->when(
                ${$request}->query('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('vm.vm_id', '<', $request->query('last_id'));
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
