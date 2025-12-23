<?php

namespace App\Http\Controllers\Customer\Vehicle;

use App\Attributes\PermissionAction;
use App\Http\Controllers\Controller;
use App\Models\Vehicle\VehicleAccident;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleAccidentController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $perPage = 20;

        $this->response()->withExtras(
            ['perPage' => $perPage]
        );

        $auth = auth();

        $data = VehicleAccident::indexQuery(['cu_id' => $auth->id()])
            ->when(
                $request->query('last_id'),
                function (Builder $query) use ($request) {
                    $query->where('va.va_id', '<', $request->query('last_id'));
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
