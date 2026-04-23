<?php

namespace App\Http\Controllers\Customer\Sale;

use App\Enum\Booking\BvIsListed;
use App\Http\Controllers\Controller;
use App\Models\_\Company;
use App\Models\Sale\BookingVehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BookingVehicleController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = 20;

        $this->response()->withExtras(
            ['perPage' => $perPage],
            ['company' => Company::query()->firstOrNew()]
        );

        $data = BookingVehicle::indexQuery()
            ->where('bv.bv_is_listed', '=', BvIsListed::LISTED)
            ->when(
                $request->query('last_id'),
                function (Builder $query) use ($request) {
                    // 基于 last_id 的游标分页。
                    $query->where('bv.bv_id', '<', $request->query('last_id'));
                }
            )
            ->orderByDesc('bv.bv_id')
            ->limit($perPage)
            ->get()
        ;

        return $this->response()->withData($data)->respond();
    }

    public function show(BookingVehicle $bookingVehicle): Response
    {
        abort_unless(BvIsListed::LISTED === (int) $bookingVehicle->bv_is_listed->value, 404);

        $bookingVehicle->load('Vehicle');

        $this->response()->withExtras(
            ['company' => Company::query()->firstOrNew()]
        );

        return $this->response()->withData($bookingVehicle)->respond();
    }
}
