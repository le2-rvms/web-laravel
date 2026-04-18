<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PtIsActive;
use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('财务类型配置')]
class PaymentTypeController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function show(Request $request): Response
    {
        $this->response()->withExtras(
            // 前端配置页需要完整的类型选项。
            PaymentType::indexOptions(),
        );

        // 返回当前启用的类型作为默认选中。
        $ids = PaymentType::query()
            ->where('pt_is_active', '=', PtIsActive::ENABLED)
            ->pluck('pt_id')
            ->toArray()
        ;

        return $this->response()->withData(['selected_types' => $ids])->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'selected_types' => ['required', 'array'],
            ],
            [],
            []
        )
            ->validate()
        ;

        // 事务内统一启停，避免配置半更新。
        DB::transaction(function () use ($input) {
            // 选中项启用，未选中项统一停用，保证配置一致性。
            PaymentType::query()->whereIn('pt_id', $input['selected_types'])->update(['pt_is_active' => PtIsActive::ENABLED]);
            PaymentType::query()->whereNotIn('pt_id', $input['selected_types'])->update(['pt_is_active' => PtIsActive::DISABLED]);
        });

        return $this->response()->withData($input)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
