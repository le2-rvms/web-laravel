<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VmvStatus;
use App\Enum\Vehicle\VvPaymentStatus;
use App\Enum\Vehicle\VvProcessStatus;
use App\Http\Controllers\Controller;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('高风险违章车辆')]
class ViolationCountController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        // 构建 vehicle_violations 表的查询
        $violations = DB::table('vehicle_violations', 'vv')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vv.ve_id')
            ->leftJoin('vehicle_usages as vu', 'vu.vu_id', '=', 'vv.vu_id')
            ->leftJoin('vehicle_inspections as vi', 'vi.vi_id', '=', 'vu.start_vi_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vi.sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->select('vv.violation_datetime', 'sc.sc_id', 've.plate_no', 'cu.contact_name', 'cu.contact_phone', 'vv.ve_id', 'vv.fine_amount', 'vv.penalty_points')
            ->where('process_status', '=', VvProcessStatus::UNPROCESSED)
            ->where('payment_status', '=', VvPaymentStatus::UNPAID)
            ->whereNotNull('vv.vu_id')
        ;

        // 构建 vehicle_manual_violations 表的查询
        $manualViolations = DB::table('vehicle_manual_violations', 'vv')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'vv.ve_id')
            ->leftJoin('vehicle_usages as vu', 'vu.vu_id', '=', 'vv.vu_id')
            ->leftJoin('vehicle_inspections as vi', 'vi.vi_id', '=', 'vu.start_vi_id')
            ->leftJoin('sale_contracts as sc', 'sc.sc_id', '=', 'vi.sc_id')
            ->leftJoin('customers as cu', 'cu.cu_id', '=', 'sc.cu_id')
            ->select('vv.violation_datetime', 'sc.sc_id', 've.plate_no', 'cu.contact_name', 'cu.contact_phone', 'vv.ve_id', 'vv.fine_amount', 'vv.penalty_points')
            ->where('status', '=', VmvStatus::UNPROCESSED)
            ->whereNotNull('vv.vu_id')
        ;

        // 使用 UNION ALL 合并两个查询
        $combinedViolations = $violations->unionAll($manualViolations);

        // 封装子查询并进行聚合、筛选和排序
        $query = DB::query()
            ->fromSub($combinedViolations, 'combined_violations')
            ->select(
                'sc_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(fine_amount) as sum_fine_amount'),
                DB::raw('SUM(penalty_points) as sum_penalty_points')
            )
            ->groupBy('sc_id')
            ->havingRaw('SUM(fine_amount) > 0 OR SUM(penalty_points) > 0 OR COUNT(*) > 0')
            ->orderBy('count', 'desc')
        ;

        $paginate = new PaginateService(
            [],
            [['max(violation_datetime) desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('vmv.violation_content', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
