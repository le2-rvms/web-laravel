<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\Risk\ExpiryVehicle;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('车证到期')]
class ExpiryVehicleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $query = ExpiryVehicle::indexQuery();

        $paginate = new PaginateService(
            [],
            // 按证照到期时间升序，便于优先处理。
            [['ve.ve_license_valid_until_date', 'asc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    // 资源路由占位：仅提供列表查询。
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
