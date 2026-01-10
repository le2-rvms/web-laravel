<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\SaleContract\ScRentalType_Short;
use App\Enum\SaleContract\SctPaymentDay_Month;
use App\Enum\SaleContract\SctPaymentDay_Week;
use App\Enum\SaleContract\SctPaymentPeriod;
use App\Enum\SaleContract\SctRentalType;
use App\Enum\SaleContract\SctStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleContractTpl;
use App\Rules\PaymentDayCheck;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('签约模板')]
class SaleContractTplController extends Controller
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
        );

        $query = SaleContractTpl::indexQuery();

        $paginate = new PaginateService(
            [],
            [['sct.sct_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder
                        ->where('sct.sct_name', 'ilike', '%'.$value.'%')->orWhere('sct.sct_remark', 'ilike', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();
        $saleContractTpl = new SaleContractTpl([]);

        $this->response()->withExtras(
        );

        return $this->response()->withData($saleContractTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(SaleContractTpl $saleContractTpl): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($saleContractTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(SaleContractTpl $saleContractTpl): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($saleContractTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?SaleContractTpl $saleContractTpl): Response
    {
        $input1 = $request->validate(
            [
                'sct_rental_type'    => ['bail', 'required', Rule::in(SctRentalType::label_keys())],
                'sct_payment_period' => ['bail', 'nullable', 'string', Rule::in(SctPaymentPeriod::label_keys())],
            ],
            [],
            trans_property(SaleContractTpl::class)
        );

        // 长租与短租字段规则不同，后续校验基于该开关。
        $is_long_term = SctRentalType::LONG_TERM === $input1['sct_rental_type'];

        $input = Validator::make(
            $request->all(),
            [
                'sct_name'                  => ['bail', 'required', 'max:255'],
                'sct_no_prefix'             => ['bail', 'nullable', 'string', 'max:50'],
                'sct_free_days'             => ['bail', 'nullable', 'int:4'],
                'sct_installments'          => ['bail', 'nullable', 'integer', 'min:1'],
                'sct_deposit_amount'        => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'sct_management_fee_amount' => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'sct_rent_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                // 短租专用费用字段，长租场景排除。
                'sct_insurance_base_fee_amount'       => ['bail', Rule::excludeIf($is_long_term), 'nullable', 'decimal:0,2', 'gte:0'],
                'sct_insurance_additional_fee_amount' => ['bail', Rule::excludeIf($is_long_term), 'nullable', 'decimal:0,2', 'gte:0'],
                'sct_other_fee_amount'                => ['bail', Rule::excludeIf($is_long_term), 'nullable', 'decimal:0,2', 'gte:0'],
                // 付款日校验依赖付款周期类型。
                'sct_payment_day'   => ['bail', 'nullable', 'integer', new PaymentDayCheck($input1['sct_payment_period'])],
                'sct_cus_1'         => ['bail', 'nullable', 'max:255'],
                'sct_cus_2'         => ['bail', 'nullable', 'max:255'],
                'sct_cus_3'         => ['bail', 'nullable', 'max:255'],
                'sct_discount_plan' => ['bail', 'nullable', 'max:255'],
                'sct_remark'        => ['bail', 'nullable', 'max:255'],
            ]
            + Uploader::validator_rule_upload_array('sct_additional_photos')
            + Uploader::validator_rule_upload_object('sct_additional_file'),
            [],
            trans_property(SaleContractTpl::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        $input = $input1 + $input;

        DB::transaction(function () use (&$input, &$saleContractTpl) {
            if (null === $saleContractTpl) {
                /** @var SaleContractTpl $saleContractTpl */
                $saleContractTpl = SaleContractTpl::query()->create($input);
            } else {
                $saleContractTpl->update($input);
            }
        });

        $saleContractTpl->refresh();

        return $this->response()->withData($saleContractTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(SaleContractTpl $saleContractTpl): Response
    {
        $saleContractTpl->delete();

        return $this->response()->withData($saleContractTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function status(Request $request, SaleContractTpl $saleContractTpl): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'sct_status' => ['bail', 'required', Rule::in(SctStatus::label_keys())],
            ],
            [],
            trans_property(SaleContractTpl::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        $saleContractTpl->update([
            'sct_status' => $input['sct_status'],
        ]);

        return $this->response()->withData($saleContractTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'sale_contract_tpl',
            [
                'sct_additional_photos',
                'sct_additional_file',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            SctStatus::options(),
            SctRentalType::options(),
            //            ScRentalType_Short::options(),
            SctPaymentPeriod::options(),
            SctPaymentDay_Month::options(),
            SctPaymentDay_Week::options(),
        );
    }
}
