<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Sale\SoPaymentDay_Month;
use App\Enum\Sale\SoPaymentDay_Week;
use App\Enum\Sale\SoPaymentDayType;
use App\Enum\Sale\SoRentalType;
use App\Enum\Sale\SoRentalType_Short;
use App\Enum\Sale\SotSotStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleOrderTpl;
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
class SaleOrderTplController extends Controller
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

        $query = SaleOrderTpl::indexQuery();

        $paginate = new PaginateService(
            [],
            [['sot.sot_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder
                        ->where('sot.sot_name', 'like', '%'.$value.'%')
                        ->orWhere('sot.so_remark', 'like', '%'.$value.'%')
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
        $saleOrderTpl = new SaleOrderTpl([]);

        $this->response()->withExtras(
        );

        return $this->response()->withData($saleOrderTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(SaleOrderTpl $saleOrderTpl): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($saleOrderTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(SaleOrderTpl $saleOrderTpl): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($saleOrderTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?SaleOrderTpl $saleOrderTpl): Response
    {
        $input1 = $request->validate(
            [
                'rental_type'      => ['bail', 'required', Rule::in(SoRentalType::label_keys())],
                'payment_day_type' => ['bail', 'nullable', 'string', Rule::in(SoPaymentDayType::label_keys())],
            ],
            [],
            trans_property(SaleOrderTpl::class)
        );

        $is_long_term = SoRentalType::LONG_TERM === $input1['rental_type'];

        $validator = Validator::make(
            $request->all(),
            [
                'sot_name'                        => ['bail', 'required', 'max:255'],
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
                'so_remark'                       => ['bail', 'nullable', 'max:255'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_object('additional_file'),
            [],
            trans_property(SaleOrderTpl::class)
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

        DB::transaction(function () use (&$input, &$saleOrderTpl) {
            if (null === $saleOrderTpl) {
                /** @var SaleOrderTpl $saleOrderTpl */
                $saleOrderTpl = SaleOrderTpl::query()->create($input);
            } else {
                $saleOrderTpl->update($input);
            }
        });

        $saleOrderTpl->refresh();

        return $this->response()->withData($saleOrderTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(SaleOrderTpl $saleOrderTpl): Response
    {
        $saleOrderTpl->delete();

        return $this->response()->withData($saleOrderTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function status(Request $request, SaleOrderTpl $saleOrderTpl): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'sot_status' => ['bail', 'required', Rule::in(SotSotStatus::label_keys())],
            ],
            [],
            trans_property(SaleOrderTpl::class)
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

        $saleOrderTpl->update([
            'sot_status' => $input['sot_status'],
        ]);

        return $this->response()->withData($saleOrderTpl)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'sale_order_tpl',
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
            SoRentalType::options(),
            SoRentalType_Short::options(),
            SoPaymentDayType::options(),
            SoPaymentDay_Month::options(),
            SoPaymentDay_Week::options(),
        );
    }
}
