<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Delivery\DlDcKey;
use App\Enum\Delivery\DlSendStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery\DeliveryLog;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('消息')]
class DeliveryLogController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            DlDcKey::labelOptions(),
            DlSendStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = DeliveryLog::indexQuery();

        $paginate = new PaginateService(
            [],
            // 按最新发送记录排序。
            [['dl.dl_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    // 根据消息标题关键字搜索发送记录。
                    $builder->where('dc.dc_title', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    public function create(Request $request): Response
    {
        $this->options();

        $deliveryLog = new DeliveryLog([
        ]);

        $this->response()->withExtras(
        );

        return $this->response()->withData($deliveryLog)->respond();
    }

    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    public function show(DeliveryLog $deliveryLog): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($deliveryLog)->respond();
    }

    public function edit(DeliveryLog $deliveryLog): Response
    {
        $this->options();

        return $this->response()->withData($deliveryLog)->respond();
    }

    public function update(Request $request, ?DeliveryLog $deliveryLog): Response
    {
        // 日志只读，保留资源路由占位。
        return $this->response()->withData($deliveryLog)->respond();
    }

    public function destroy(DeliveryLog $deliveryLog): Response
    {
        return $this->response()->withData($deliveryLog)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            DlDcKey::options(),
            DlSendStatus::options(),
        );
    }
}
