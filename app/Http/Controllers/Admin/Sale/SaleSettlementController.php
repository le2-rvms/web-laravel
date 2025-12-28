<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\Payment\SsDeleteOption;
use App\Enum\Sale\DtExportType;
use App\Enum\Sale\DtStatus;
use App\Enum\Sale\DtType;
use App\Enum\Sale\SsReturnStatus;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Payment\Payment;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleContract;
use App\Models\Sale\SaleSettlement;
use App\Models\Sale\VehicleTmp;
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
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('退车结算')]
class SaleSettlementController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            SsDeleteOption::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        /** @var Admin $admin */
        $admin = auth()->user();

        $query   = SaleSettlement::indexQuery();
        $columns = SaleSettlement::indexColumns();

        // 车队查询条件
        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('cu.cu_team_id', $admin->a_team_ids)->orWhereNull('cu.cu_team_id');
            });
        }

        $paginate = new PaginateService(
            [],
            [['ss.ss_id', 'desc']],
            ['kw', 'ss_return_datetime', 'ss_return_status'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.ve_plate_no', 'like', '%'.$value.'%')
                            ->orWhere('cu.cu_contact_name', 'like', '%'.$value.'%')
                            ->orWhere('cu.cu_contact_phone', 'like', '%'.$value.'%')
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

        $saleContract = null;

        $input = Validator::make(
            $request->all(),
            [
                'sc_id' => ['required', 'integer'],
            ],
            [],
            trans_property(SaleSettlement::class)
        )
            ->after(function ($validator) use ($request, &$saleContract) {
                if ($validator->failed()) {
                    return;
                }

                /** @var SaleContract $saleContract */
                $saleContract = SaleContract::query()->findOrFail($request->input('sc_id'));

                $saleSettlement = $saleContract->SaleSettlement;
                if ($saleSettlement) {
                    $validator->errors()->add('sc_id', '退车结算已存在');

                    return;
                }

                // 续租链路校验：全部合同须已签约，且只能在最新合同上结算。
                $groupSaleContractList = SaleContract::query()
                    ->where('sc_group_no', '=', $saleContract->sc_group_no)
                    ->orderByDesc('sc_group_seq')
//                    ->whereNotIn('sc_status', [ScStatus::SIGNED])
                    ->get()
                ;

                /** @var SaleContract $groupSaleContract */
                foreach ($groupSaleContractList as $groupSaleContract) {
                    if (ScStatus::SIGNED !== $groupSaleContract->sc_status->value) {
                        $validator->errors()->add('sc_id', '存在非已签约的续租，不能退车。');

                        return;
                    }
                }

                /** @var SaleContract $first */
                $first = $groupSaleContractList->first();
                if ($first->sc_id !== $saleContract->sc_id) {
                    $validator->errors()->add('sc_id', '当前合同已有续租，请在最新的续租合同上办理结算');
                }
            })
            ->validate()
        ;

        $groupContractIds = SaleContract::query()
            ->where('sc_group_no', '=', $saleContract->sc_group_no)
            ->pluck('sc_id')
            ->toArray()
        ;

        $saleSettlement = $saleContract->SaleSettlement;
        if (!$saleSettlement) { // 是新增
            // 汇总合同组押金，作为结算默认值（应收/实收）。
            $depositPayments = Payment::query()
                ->whereIn('p_sc_id', $groupContractIds)
                ->where('p_pt_id', '=', PPtId::DEPOSIT)
                ->where('p_is_valid', '=', PIsValid::VALID)
                ->get()
            ;

            $depositAmount = $depositPayments->reduce(function ($carry, Payment $payment) {
                return bcadd($carry, $payment->p_should_pay_amount ?? '0', 2);
            }, '0');

            $receivedDeposit = $depositPayments->filter(function (Payment $payment) {
                return PPayStatus::PAID === $payment->p_pay_status->value;
            })->reduce(function ($carry, Payment $payment) {
                return bcadd($carry, $payment->p_actual_pay_amount ?? '0', 2);
            }, '0');

            $saleSettlement = new SaleSettlement([
                'ss_sc_id'                      => $saleContract->sc_id,
                'ss_deposit_amount'             => $depositAmount, // 押金应收金额
                'ss_received_deposit'           => $receivedDeposit, // 押金实收金额
                'ss_early_return_penalty'       => '0',
                'ss_overdue_inspection_penalty' => '0',
                'ss_overdue_rent'               => '0',
                'ss_overdue_penalty'            => '0',
                'ss_accident_depreciation_fee'  => '0',
                'ss_insurance_surcharge'        => '0',
                'ss_violation_withholding_fee'  => '0',
                'ss_violation_penalty'          => '0',
                'ss_repair_fee'                 => '0',
                'ss_insurance_deductible'       => '0',
                'ss_forced_collection_fee'      => '0',
                'ss_other_deductions'           => '0',
                'ss_refund_amount'              => '0',
                'ss_settlement_amount'          => '0',
                'ss_return_datetime'            => now(),
                'ss_delete_option'              => SsDeleteOption::DELETE,
                'ss_processed_by'               => Auth::id(),
            ]);

            $this->response()->withExtras(
                Admin::optionsWithRoles(),
            );
        } else { // 是修改
            $this->response()->withExtras(
                DocTpl::options(function (Builder $query) {
                    $query->where('dt.dt_type', '=', DtType::SALE_SETTLEMENT);
                }),
                Admin::optionsWithRoles(),
            );
        }

        $saleContract->load('Customer', 'Vehicle', 'Payments');

        $this->response()->withExtras(
            ['saleContract' => $saleContract],
            SaleContract::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleTmp::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleInspection::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            Payment::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            Payment::indexStat(),
            SaleSettlement::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleUsage::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleRepair::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleRepair::indexStat(),
            VehicleViolation::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleManualViolation::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleManualViolation::indexStat(),
        );

        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, SaleSettlement $saleSettlement, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(DocTpl::class, 'dt_id')->where('dt_type', DtType::SALE_SETTLEMENT)->where('dt_status', DtStatus::ENABLED)],
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
            Admin::optionsWithRoles(),
        );

        $saleContract = $saleSettlement->SaleContract;

        $groupContractIds = SaleContract::query()
            ->where('sc_group_no', '=', $saleContract->sc_group_no)
            ->pluck('sc_id')
            ->toArray()
        ;

        $saleContract->load('Customer', 'Vehicle', 'Payments');

        $this->response()->withExtras(
            ['saleContract' => $saleContract],
            SaleContract::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleTmp::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleInspection::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            Payment::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            Payment::indexStat(),
            SaleSettlement::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleUsage::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleRepair::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleRepair::indexStat(),
            VehicleViolation::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleManualViolation::indexList(function (Builder $query) use ($groupContractIds) {
                $query->whereIn('sc.sc_id', $groupContractIds);
            }),
            VehicleManualViolation::indexStat(),
        );

        return $this->response()->withData($saleSettlement)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?SaleSettlement $saleSettlement): Response
    {
        $saleContract = null;

        $input = Validator::make(
            $request->all(),
            [
                'ss_sc_id'                      => ['required', 'integer'],
                'ss_deposit_amount'             => ['nullable', 'numeric'],
                'ss_received_deposit'           => ['nullable', 'numeric'],
                'ss_early_return_penalty'       => ['nullable', 'numeric'],
                'ss_overdue_inspection_penalty' => ['nullable', 'numeric'],
                'ss_overdue_rent'               => ['nullable', 'numeric'],
                'ss_overdue_penalty'            => ['nullable', 'numeric'],
                'ss_accident_depreciation_fee'  => ['nullable', 'numeric'],
                'ss_insurance_surcharge'        => ['nullable', 'numeric'],
                'ss_violation_withholding_fee'  => ['nullable', 'numeric'],
                'ss_violation_penalty'          => ['nullable', 'numeric'],
                'ss_repair_fee'                 => ['nullable', 'numeric'],
                'ss_insurance_deductible'       => ['nullable', 'numeric'],
                'ss_forced_collection_fee'      => ['nullable', 'numeric'],
                'ss_other_deductions'           => ['nullable', 'numeric'],
                'ss_other_deductions_remark'    => ['nullable', 'string'],
                'ss_refund_amount'              => ['nullable', 'numeric'],
                'ss_refund_details'             => ['nullable', 'string'],
                'ss_settlement_amount'          => ['nullable', 'numeric'],
                'ss_deposit_return_amount'      => ['nullable', 'numeric'],
                'ss_deposit_return_date'        => ['required', 'date'],
                'ss_return_datetime'            => ['required', 'date'],
                'ss_delete_option'              => ['required', Rule::in(SsDeleteOption::label_keys())],
                'ss_remark'                     => ['nullable', 'string'],
                'ss_processed_by'               => ['bail', 'nullable', 'integer', Rule::exists(Admin::class, 'id')],
            ]
            + Uploader::validator_rule_upload_array('ss_additional_photos'),
            [],
            trans_property(SaleSettlement::class)
        )->after(function ($validator) use ($request, &$saleSettlement, &$saleContract) {
            if ($validator->failed()) {
                return;
            }
            // 计算结算费
            $result = '0';
            foreach (SaleSettlement::calcOpts as $key => $opt) {
                $value  = $request->input($key);
                $result = bcadd($result, $opt.$value, 2);
            }

            // 结算总额为正：应收结算费；为负：应退押金。
            if (bccomp($result, '0', 2) > 0) {
                if (0 !== bccomp($request->input(['ss_settlement_amount']), $result, 2)) {
                    $validator->errors()->add('settlement_amount', '结算费计算错误');

                    return;
                }
            }

            if (bccomp($result, '0', 2) < 0) {
                if (0 !== bccomp($request->input(['ss_deposit_return_amount']), bcmul($result, '-1', 2), 2)) {
                    $validator->errors()->add('deposit_return_amount', '应退押金金额计算错误');

                    return;
                }
            }

            if ($saleSettlement) { // 修改的时候
                if (SsReturnStatus::CONFIRMED === $saleSettlement->ss_return_status->value) {
                    $validator->errors()->add('ss_return_status', '已审核，不能被修改');

                    return;
                }
            }

            $saleContract = SaleContract::query()->findOrFail($request->input('ss_sc_id'));

            if (!$saleContract->check_status([ScStatus::SIGNED], $validator)) {
                return;
            }

            // vehicle
            $vehicle = $saleContract->Vehicle;

            $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [], $validator);
            if (!$pass) {
                return;
            }

            // sc_group_no
            $groupSaleContractList = SaleContract::query()
                ->where('sc_group_no', '=', $saleContract->sc_group_no)
                ->orderByDesc('sc_group_seq')
//                    ->whereNotIn('sc_status', [ScStatus::SIGNED])
                ->get()
            ;

            /** @var SaleContract $groupSaleContract */
            foreach ($groupSaleContractList as $groupSaleContract) {
                if (ScStatus::SIGNED !== $groupSaleContract->sc_status->value) {
                    $validator->errors()->add('sc_id', '存在非已签约的续租，不能退车。');

                    return;
                }
            }

            /** @var SaleContract $first */
            $first = $groupSaleContractList->first();
            if ($first->sc_id !== $saleContract->sc_id) {
                $validator->errors()->add('sc_id', '当前合同已有续租，请在最新的续租合同上办理结算');

                return;
            }
        })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$saleSettlement) {
            $_saleSettlement = SaleSettlement::query()->updateOrCreate(
                ['ss_sc_id' => $input['ss_sc_id']],
                $input + ['ss_return_status' => SsReturnStatus::UNCONFIRMED],
            );

            $saleSettlement = $_saleSettlement;
        });

        return $this->response()->withData($saleSettlement)->respond();
    }

    public function destroy(SaleSettlement $saleSettlement) {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'sale_settlement', ['ss_additional_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            SsDeleteOption::options(),
            $with_group_count ? SsReturnStatus::options_with_count(SaleSettlement::class) : SsReturnStatus::options(),
        );
    }
}
