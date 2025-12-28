<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\IoType;
use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentAccount;
use App\Models\Payment\PaymentInout;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('账户流水')]
class InoutController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            // 账号下拉选项供筛选使用。
            PaymentAccount::options(),
        );

        $query   = PaymentInout::indexQuery();
        $columns = PaymentInout::indexColumns();

        $paginate = new PaginateService(
            [],
            [],
            ['kw', 'io_type', 'io_pa_id', 'io_occur_datetime'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                // Keyword search across customer, vehicle, remark, and contract.
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('cu.cu_contact_name', 'like', '%'.$value.'%')
                            ->orWhere('ve.ve_plate_no', 'like', '%'.$value.'%')
                            ->orWhere('p.p_remark', 'like', '%'.$value.'%')
                            ->orWhere('sc.sc_no', 'like', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            // Include counts for filter UI when requested.
            $with_group_count ? IoType::options_with_count(PaymentInout::class) : IoType::options(),
        );
    }
}
