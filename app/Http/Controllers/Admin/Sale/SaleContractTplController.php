<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Sale\ScPaymentDay_Month;
use App\Enum\Sale\ScPaymentDay_Week;
use App\Enum\Sale\ScPaymentDayType;
use App\Enum\Sale\ScRentalType;
use App\Enum\Sale\ScRentalType_Short;
use App\Enum\Sale\SctSctStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleContractTpl;
use App\Rules\PaymentDayCheck;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                        ->where('sct.sct_name', 'like', '%'.$value.'%')
                        ->orWhere('sct.sc_remark', 'like', '%'.$value.'%')
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
                'rental_type'      => ['bail', 'required', Rule::in(ScRentalType::label_keys())],
                'payment_day_type' => ['bail', 'nullable', 'string', Rule::in(ScPaymentDayType::label_keys())],
            ],
            [],
            trans_property(SaleContractTpl::class)
        );

        $is_long_term = ScRentalType::LONG_TERM === $input1['rental_type'];

        $validator = Validator::make(
            $request->all(),
            [
                'sct_name'                        => ['bail', 'required', 'max:255'],
                'contract_number_prefix'          => ['bail', 'nullable', 'string', 'max:50'],
                'free_days'                       => ['bail', 'nullable', 'int:4'],
                'installments'                    => ['bail', 'nullable', 'integer', 'min:1'],
                'deposit_amount'                  => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'management_fee_amount'           => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'rent_amount'                     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'insurance_base_fee_amount'       => ['bail', Rule::excludeIf($is_long_term), 'nullable', 'decimal:0,2', 'gte:0'],
                'insurance_additional_fee_amount' => ['bail', Rule::excludeIf($is_long_term), 'nullable', 'decimal:0,2', 'gte:0'],
                'other_fee_amount'                => ['bail', Rule::excludeIf($is_long_term), 'nullable', 'decimal:0,2', 'gte:0'],
                'payment_day'                     => ['bail', 'nullable', 'integer', new PaymentDayCheck($input1['payment_day_type'])],
                'cus_1'                           => ['bail', 'nullable', 'max:255'],
                'cus_2'                           => ['bail', 'nullable', 'max:255'],
                'cus_3'                           => ['bail', 'nullable', 'max:255'],
                'discount_plan'                   => ['bail', 'nullable', 'max:255'],
                'sc_remark'                       => ['bail', 'nullable', 'max:255'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_object('additional_file'),
            [],
            trans_property(SaleContractTpl::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $input1 + $validator->validated();

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
        $validator = Validator::make(
            $request->all(),
            [
                'sct_status' => ['bail', 'required', Rule::in(SctSctStatus::label_keys())],
            ],
            [],
            trans_property(SaleContractTpl::class)
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
                'additional_photos',
                'additional_file',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            ScRentalType::options(),
            ScRentalType_Short::options(),
            ScPaymentDayType::options(),
            ScPaymentDay_Month::options(),
            ScPaymentDay_Week::options(),
        );
    }
}
