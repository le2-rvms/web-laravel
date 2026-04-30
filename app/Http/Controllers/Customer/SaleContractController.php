<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Risk\ExpiryDriver;
use App\Models\Risk\ExpiryVehicle;
use App\Models\Sale\SaleContract;
use App\Models\Sale\VehicleTemp;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleSchedule;
use App\Models\Vehicle\VehicleViolation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SaleContractController extends Controller
{
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
                    $query->where('sc.sc_id', '>', $request->query('last_id'));
                }
            )
            ->orderBy('sc.sc_id')
            ->limit($perPage)
            ->get()
        ;

        return $this->response()->withData($data)->respond();
    }

    public function show(SaleContract $saleContract): Response
    {
        // 仅允许客户查看自己的合同，避免通过 ID 越权读取。
        abort_unless((int) $saleContract->sc_cu_id === (int) auth()->id(), 404);

        $saleContract->load('Customer', 'Vehicle', 'Payments');

        $groupContractIds = SaleContract::query()
            ->where('sc_group_no', '=', $saleContract->sc_group_no)
            ->pluck('sc_id')
            ->toArray()
        ;

        $this->response()->withExtras(
            VehicleTemp::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleSchedule::indexList(function (Builder $query) {
                $query->whereIn('ve.ve_id', SaleContract::CustomerHasVeId());
            }),
            ExpiryDriver::indexList(function (Builder $query) {
                $query->where('cu.cu_id', '=', auth()->id());
            }),
            ExpiryVehicle::indexList(function (Builder $query) {
                $query->whereIn('ve.ve_id', SaleContract::CustomerHasVeId());
            }),
            VehicleAccident::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            Payment::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds)->orderby('p.p_id');
            }),
            Payment::indexStat(),
            VehicleMaintenance::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleRepair::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleRepair::indexStat(),
            VehicleViolation::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleViolation::indexStat(),
            VehicleManualViolation::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleManualViolation::indexStat(),
        );

        return $this->response()->withData($saleContract)->respond();
    }
}
