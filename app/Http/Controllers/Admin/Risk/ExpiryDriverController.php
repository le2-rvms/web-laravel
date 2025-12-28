<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Customer\CuiGender;
use App\Enum\Customer\CuType;
use App\Http\Controllers\Controller;
use App\Services\PaginateService;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('司机证照到期')]
class ExpiryDriverController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        // 默认查看未来 N 天游离到期证件的司机。
        $days = $request->input('days', 30);

        $targetDate = Carbon::today()->addDays($days)->toDateString();

        $query = DB::query()
            ->from('customer_individuals', 'cui')
            ->leftJoin('customers as cu', function (JoinClause $join) {
                $join->on('cui.cui_cu_id', '=', 'cu.cu_id')
                    ->where('cu.cu_type', '=', CuType::INDIVIDUAL)
                ;
            })
//            ->where('cu.cu_type', CuCustomerType::INDIVIDUAL)
//            ->where(function ($q) use ($targetDate) {
            // 驾驶证或身份证任一到期即纳入结果。
            ->where('cui.cui_driver_license_expiry_date', '<=', $targetDate)
            ->orWhere('cui.cui_id_expiry_date', '<=', $targetDate)
//                ;
//            })
            ->select('cu.*', 'cui.*')
            ->addSelect(
                // 追加枚举 label 字段，便于前端直用。
                DB::raw(CuType::toCaseSQL()),
                DB::raw(CuiGender::toCaseSQL()),
            )
        ;

        $paginate = new PaginateService(
            [],
            [['cu.cu_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    public function create() {}

    public function store(Request $request) {}

    public function show(string $id) {}

    public function edit(string $id) {}

    public function update(Request $request, string $id) {}

    public function destroy(string $id) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
