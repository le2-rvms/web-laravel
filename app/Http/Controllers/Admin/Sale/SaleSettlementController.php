<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\RpPtId;
use App\Enum\Payment\RsDeleteOption;
use App\Enum\Sale\DtDtExportType;
use App\Enum\Sale\DtDtStatus;
use App\Enum\Sale\DtDtType;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\SsReturnStatus;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Staff;
use App\Models\Payment\Payment;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleReplacement;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleUsage;
use App\Models\Vehicle\VehicleViolation;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('退车结算管理')]
class SaleSettlementController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            RsDeleteOption::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query   = SaleSettlement::indexQuery();
        $columns = SaleSettlement::indexColumns();

        $paginate = new PaginateService(
            [],
            [['ss.ss_id', 'desc']],
            ['kw', 'rs_return_datetime', 'rs_return_status'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('cu.contact_name', 'like', '%'.$value.'%')
                            ->orWhere('cu.contact_phone', 'like', '%'.$value.'%')
                            ->orWhere('ss.ss_remark', 'like', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras();

        $saleOrder = null;

        $validator = Validator::make(
            $request->all(),
            [
                'so_id' => ['required', 'integer'],
            ],
            [],
            trans_property(SaleSettlement::class)
        )
            ->after(function ($validator) use ($request, &$saleOrder) {
                if (!$validator->failed()) {
                    /** @var SaleOrder $saleOrder */
                    $saleOrder = SaleOrder::query()->findOrFail($request->input('so_id'));
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $saleSettlement = $saleOrder->SaleSettlement;
        if (!$saleSettlement) { // 是新增
            /** @var Payment $payment */
            $payment = $saleOrder->Payments()->where('pt_id', '=', RpPtId::DEPOSIT)->first();

            $saleSettlement = new SaleSettlement([
                'so_id'                      => $saleOrder->so_id,
                'deposit_amount'             => $payment->should_pay_amount ?? 0, // 押金应收金额
                'received_deposit'           => $payment->actual_pay_amount ?? 0, // 押金实收金额
                'early_return_penalty'       => '0',
                'overdue_inspection_penalty' => '0',
                'overdue_rent'               => '0',
                'overdue_penalty'            => '0',
                'accident_depreciation_fee'  => '0',
                'insurance_surcharge'        => '0',
                'violation_withholding_fee'  => '0',
                'violation_penalty'          => '0',
                'repair_fee'                 => '0',
                'insurance_deductible'       => '0',
                'forced_collection_fee'      => '0',
                'other_deductions'           => '0',
                'refund_amount'              => '0',
                'settlement_amount'          => '0',
                'return_datetime'            => now(),
                'delete_option'              => RsDeleteOption::DELETE,
                'processed_by'               => Auth::id(),
            ]);

            $this->response()->withExtras(
                Staff::optionsWithRoles(),
            );
        } else { // 是修改
            $this->response()->withExtras(
                DocTpl::options(function (Builder $query) {
                    $query->where('dt.dt_type', '=', DtDtType::SALE_SETTLEMENT);
                }),
                Staff::optionsWithRoles(),
            );
        }

        $saleOrder->load('Customer', 'Vehicle', 'Payments');

        $this->response()->withExtras(
            ['sale_order' => $saleOrder],
            VehicleReplacement::kvList(so_id: $saleOrder->so_id),
            VehicleInspection::kvList(so_id: $saleOrder->so_id),
            Payment::kvList(so_id: $saleOrder->so_id),
            Payment::kvStat(),
            SaleSettlement::kvList(so_id: $saleOrder->so_id),
            VehicleUsage::kvList(so_id: $saleOrder->so_id),
            VehicleRepair::kvList(so_id: $saleOrder->so_id),
            VehicleRepair::kvStat(),
            VehicleViolation::kvList(so_id: $saleOrder->so_id),
            VehicleViolation::kvStat(),
            VehicleManualViolation::kvList(so_id: $saleOrder->so_id),
            VehicleManualViolation::kvStat(),
        );

        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, SaleSettlement $saleSettlement, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(DocTpl::class)->where('dt_type', DtDtType::SALE_SETTLEMENT)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $saleSettlement);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(Request $request, SaleSettlement $saleSettlement): Response
    {
        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(SaleSettlement $saleSettlement): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?SaleSettlement $saleSettlement): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'so_id'                      => ['required', 'integer'],
                'deposit_amount'             => ['nullable', 'numeric'],
                'received_deposit'           => ['nullable', 'numeric'],
                'early_return_penalty'       => ['nullable', 'numeric'],
                'overdue_inspection_penalty' => ['nullable', 'numeric'],
                'overdue_rent'               => ['nullable', 'numeric'],
                'overdue_penalty'            => ['nullable', 'numeric'],
                'accident_depreciation_fee'  => ['nullable', 'numeric'],
                'insurance_surcharge'        => ['nullable', 'numeric'],
                'violation_withholding_fee'  => ['nullable', 'numeric'],
                'violation_penalty'          => ['nullable', 'numeric'],
                'repair_fee'                 => ['nullable', 'numeric'],
                'insurance_deductible'       => ['nullable', 'numeric'],
                'forced_collection_fee'      => ['nullable', 'numeric'],
                'other_deductions'           => ['nullable', 'numeric'],
                'other_deductions_remark'    => ['nullable', 'string'],
                'refund_amount'              => ['nullable', 'numeric'],
                'refund_details'             => ['nullable', 'string'],
                'settlement_amount'          => ['nullable', 'numeric'],
                'deposit_return_amount'      => ['nullable', 'numeric'],
                'deposit_return_date'        => ['nullable', 'date'],
                'return_datetime'            => ['required', 'date'],
                'delete_option'              => ['required', Rule::in(RsDeleteOption::label_keys())],
                'ss_remark'                  => ['nullable', 'string'],
                'processed_by'               => ['bail', 'nullable', 'integer', Rule::exists(Staff::class, 'id')],
            ]
            + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(SaleSettlement::class)
        )->after(function ($validator) use ($request, &$saleSettlement, &$saleOrder) {
            if (!$validator->failed()) {
                // 计算结算费
                $result = '0';
                foreach (SaleSettlement::calcOpts as $key => $opt) {
                    $value  = $request->input($key);
                    $result = bcadd($result, $opt.$value, 2);
                }

                if (bccomp($result, '0', 2) > 0) {
                    if (0 !== bccomp($request->input(['settlement_amount']), $result, 2)) {
                        $validator->errors()->add('settlement_amount', '结算费计算错误');

                        return;
                    }
                }

                if (bccomp($result, '0', 2) < 0) {
                    if (0 !== bccomp($request->input(['deposit_return_amount']), bcmul($result, '-1', 2), 2)) {
                        $validator->errors()->add('deposit_return_amount', '应退押金金额计算错误');

                        return;
                    }
                }

                if ($saleSettlement) { // 修改的时候
                    if (SsReturnStatus::CONFIRMED === $saleSettlement->return_status) {
                        $validator->errors()->add('return_status', '已审核，不能被修改');
                    }
                }

                $saleOrder = SaleOrder::query()->findOrFail($request->input('so_id'));

                if (!$saleOrder->check_order_status([SoOrderStatus::SIGNED], $validator)) {
                    return;
                }

                // vehicle
                $vehicle = $saleOrder->Vehicle;

                $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [], $validator);
                if (!$pass) {
                    return;
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$saleSettlement) {
            $_saleSettlement = SaleSettlement::query()->updateOrCreate(
                array_intersect_key($input, array_flip(['so_id'])),
                $input + ['return_status' => SsReturnStatus::UNCONFIRMED],
            );

            $saleSettlement = $_saleSettlement;
        });

        return $this->response()->withData($saleSettlement)->respond();
    }

    public function destroy(SaleSettlement $saleSettlement) {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'sale_settlement', ['additional_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            RsDeleteOption::options(),
            $with_group_count ? SsReturnStatus::options_with_count(SaleSettlement::class) : SsReturnStatus::options(),
        );
    }
}
