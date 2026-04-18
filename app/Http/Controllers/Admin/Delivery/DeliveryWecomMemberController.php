<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Sale\SaleContractExt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('企业微信机器人')]
class DeliveryWecomMemberController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        // 拉取成员列表以配置企业微信展示名。
        $items = Admin::indexQuery()
            ->select('id', 'name', 'a_wecom_name')
            ->orderByDesc('a.id')
            ->get()
        ;

        return $this->response()->withData(compact('items'))->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'items'                => ['bail', 'nullable', 'array'],
                'items.*.id'           => ['bail', 'required', 'integer', Rule::exists(Admin::class, 'id')],
                'items.*.a_wecom_name' => ['bail', 'nullable', 'max:255'],
            ],
            [],
            trans_property(SaleContractExt::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        // 批量更新成员配置，并清理未提交条目。
        DB::transaction(function () use (&$input) {
            foreach ($input['items'] as $item) {
                Admin::query()->find($item['id'])->update($item);
            }
            // 未在提交列表内的成员将被删除，避免残留无效配置。
            Admin::query()->whereNotIn('id', array_column($input['items'], 'id'))->delete();
        });

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
