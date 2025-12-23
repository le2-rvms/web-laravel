<?php

namespace App\Http\Controllers\Customer\Risk;

use App\Http\Controllers\Controller;
use App\Models\Risk\ExpiryDriver;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
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

        $days = 30;

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        $data = ExpiryDriver::indexQuery()
            ->where('cu.cu_id', '=', $auth->id())
            ->where(function (Builder $q) use ($targetDate) {
                $q->where('cui.cui_driver_license_expiry_date', '<=', $targetDate)
                    ->orWhere('cui_id_expiry_date', '<=', $targetDate)
                ;
            })
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
