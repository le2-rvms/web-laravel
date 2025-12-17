<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\SaleContract\ScPaymentPeriod;
use App\Enum\SaleContract\ScRentalType_ShortOnlyShort;
use App\Enum\SaleContract\ScStatus;
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
                    $builder->whereIn('sc.sc_status', [ScStatus::SIGNED]);
                }
            ),
        );

        $items = SaleContractExt::indexQuery()
            ->orderByDesc('sce.sce_id')
            ->whereIn('sc.sc_status', [ScStatus::SIGNED])
            ->addSelect(
                DB::raw(sprintf(
                    "CONCAT(cu.cu_contact_name,'|',%s,'|', ve.ve_plate_no ,'|',  %s, %s ,'|', %s ) as text,sc.sc_id as value",
                    '((SUBSTRING(cu.cu_contact_phone, 1, 0)  || SUBSTRING(cu.cu_contact_phone, 8, 4)) )',
                    ScPaymentPeriod::toCaseSQL(hasAs: false),
                    ScRentalType_ShortOnlyShort::toCaseSQL(hasAs: false),
                    ScStatus::toCaseSQL(hasAs: false)
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
                'items.*.sc_id'               => ['bail', 'required', 'integer', Rule::exists(SaleContract::class, 'sc_id')],
                'items.*.sce_wecom_group_url' => ['bail', 'required', 'max:255'],
            ],
            [],
            trans_property(SaleContractExt::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
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
