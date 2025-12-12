<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\IoIoType;
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
            PaymentAccount::options(),
        );

        $query   = PaymentInout::indexQuery();
        $columns = PaymentInout::indexColumns();

        $paginate = new PaginateService(
            [],
            [],
            ['kw', 'io_io_type', 'io_pa_id', 'io_occur_datetime'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('cu.contact_name', 'like', '%'.$value.'%')
                            ->orWhere('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('rp.rp_remark', 'like', '%'.$value.'%')
                            ->orWhere('sc.contract_number', 'like', '%'.$value.'%')
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
            $with_group_count ? IoIoType::options_with_count(PaymentInout::class) : IoIoType::options(),
        );
    }
}
