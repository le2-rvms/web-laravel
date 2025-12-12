<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType_ShortOnlyShort;
use App\Enum\Sale\ScScStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleContract;
use App\Models\Sale\SaleContractExt;
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
            SaleContract::options(
                where: function (Builder $builder) {
                    $builder->whereIn('sc.sc_status', [ScScStatus::SIGNED]);
                }
            ),
        );

        $items = SaleContractExt::indexQuery()
            ->orderByDesc('sce.sce_id')
            ->whereIn('sc.sc_status', [ScScStatus::SIGNED])
            ->addSelect(
                DB::raw(sprintf(
                    "CONCAT(cu.contact_name,'|',%s,'|', ve.plate_no ,'|',  %s, %s ,'|', %s ) as text,sc.sc_id as value",
                    '((SUBSTRING(cu.contact_phone, 1, 0)  || SUBSTRING(cu.contact_phone, 8, 4)) )',
                    ScPaymentDayType::toCaseSQL(hasAs: false),
                    ScRentalType_ShortOnlyShort::toCaseSQL(hasAs: false),
                    ScScStatus::toCaseSQL(hasAs: false)
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
                'items.*.sc_id'               => ['bail', 'required', 'integer', Rule::exists(SaleContract::class)],
                'items.*.sce_wecom_group_url' => ['bail', 'required', 'max:255'],
            ],
            [],
            trans_property(SaleContractExt::class)
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
                SaleContractExt::query()->upsert($chunks->all(), ['sc_id'], ['sce_wecom_group_url']);
            }

            SaleContractExt::query()->whereNotIn('sc_id', $items->pluck('sc_id')->all())->delete();
        });

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
