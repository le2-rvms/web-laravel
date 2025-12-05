<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType_ShortOnlyShort;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleOrderExt;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('企业微信群机器人')]
class DeliveryWecomGroupController extends Controller
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
            SaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::SIGNED]);
                }
            ),
        );

        $items = SaleOrderExt::indexQuery()
            ->orderByDesc('soe.soe_id')
            ->whereIn('so.order_status', [SoOrderStatus::SIGNED])
            ->addSelect(
                DB::raw(sprintf(
                    "CONCAT(cu.contact_name,'|',%s,'|', ve.plate_no ,'|',  %s, %s ,'|', %s ) as text,so.so_id as value",
                    '((SUBSTRING(cu.contact_phone, 1, 0)  || SUBSTRING(cu.contact_phone, 8, 4)) )',
                    SoPaymentDayType::toCaseSQL(hasAs: false),
                    SoRentalType_ShortOnlyShort::toCaseSQL(hasAs: false),
                    SoOrderStatus::toCaseSQL(hasAs: false)
                ))
            )
            ->get()
        ;

        return $this->response()->withData(compact('items'))->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'items'                       => ['bail', 'nullable', 'array'],
                'items.*.so_id'               => ['bail', 'required', 'integer', Rule::exists(SaleOrder::class)],
                'items.*.soe_wecom_group_url' => ['bail', 'required', 'max:255'],
            ],
            [],
            trans_property(SaleOrderExt::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $items = collect($input['items']);

        DB::transaction(function () use (&$items) {
            foreach ($items->chunk(50) as $chunks) {
                SaleOrderExt::query()->upsert($chunks->all(), ['so_id'], ['soe_wecom_group_url']);
            }

            SaleOrderExt::query()->whereNotIn('so_id', $items->pluck('so_id')->all())->delete();
        });

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
