<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Exist;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\Sale\DtExportType;
use App\Enum\Sale\DtStatus;
use App\Enum\Sale\DtType;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleInspection\ViDrivingLicense;
use App\Enum\VehicleInspection\ViInspectionType;
use App\Enum\VehicleInspection\ViOperationLicense;
use App\Enum\VehicleInspection\ViPolicyCopy;
use App\Enum\VehicleInspection\ViVehicleDamageStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleUsage;
use App\Services\DocTplService;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('验车')]
class VehicleInspectionController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            ViInspectionType::labelOptions(),
            ViPolicyCopy::labelOptions(),
            ViDrivingLicense::labelOptions(),
            ViOperationLicense::labelOptions(),
            ViVehicleDamageStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $query   = VehicleInspection::indexQuery();
        $columns = VehicleInspection::indexColumns();

        /** @var Admin $admin */
        $admin = auth()->user();

        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('ve.ve_team_id', $admin->a_team_ids)->orwhereNull('ve.ve_team_id');
            });
        }

        $paginate = new PaginateService(
            [],
            [['vi.vi_id', 'desc']],
            ['kw', 'vi_inspection_type', 'vi_ve_id', 'vi_kw', 'vi_inspection_datetime'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.ve_plate_no', 'like', '%'.$value.'%')
                            ->orWhere('vi.vi_remark', 'like', '%'.$value.'%')
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
        $this->response()->withExtras(
            PPayStatus::options(),
            PaymentAccount::options(),
            Admin::optionsWithRoles(),
        );

        $vehicleInspection = new VehicleInspection([
            'vi_inspection_info'     => [],
            'vi_processed_by'        => Auth::id(),
            'vi_inspection_datetime' => now(),
        ]);

        $vehicleInspection->load('Payment');

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleInspection $vehicleInspection): Response
    {
        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleInspection $vehicleInspection): Response
    {
        $this->options();
        $this->response()->withExtras(
            DocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtType::VEHICLE_INSPECTION);
            }),
            PPayStatus::options(),
            PaymentAccount::options(),
            Admin::optionsWithRoles(),
        );

        $vehicleInspection->load('Vehicle', 'SaleContract', 'SaleContract.Customer', 'SaleContract.Vehicle', 'Payment', 'Payment.PaymentAccount');

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function doc(Request $request, VehicleInspection $vehicleInspection, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(DocTpl::class, 'dt_id')->where('dt_type', DtType::VEHICLE_INSPECTION)->where('dt_status', DtStatus::ENABLED)],
        ]);

        $vehicleInspection->load('Vehicle', 'SaleContract', 'SaleContract.Customer');

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $vehicleInspection);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleInspection $vehicleInspection): Response
    {
        $vehicle = $saleContract = null;

        $input = Validator::make(
            $request->all(),
            [
                'vi_inspection_type'       => ['bail', 'required', 'string', Rule::in(ViInspectionType::label_keys())],
                'vi_sc_id'                 => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'vi_policy_copy'           => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'vi_driving_license'       => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'vi_operation_license'     => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'vi_vehicle_damage_status' => ['bail', 'nullable', Rule::in(ViVehicleDamageStatus::label_keys())],
                'vi_inspection_datetime'   => ['bail', 'required', 'date'],
                'vi_mileage'               => ['bail', 'required', 'integer', 'min:0'],
                'vi_processed_by'          => ['bail', 'nullable', 'integer', Rule::exists(Admin::class, 'id')],
                'vi_damage_deduction'      => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'vi_remark'                => ['bail', 'nullable', 'string'],
                'vi_add_should_pay'        => ['bail', 'nullable', 'boolean'],

                'vi_inspection_info'               => ['bail', 'nullable', 'array'],
                'vi_inspection_info.*.description' => ['bail', 'nullable', 'string'],

                'payment.p_pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(PPtId::VEHICLE_DAMAGE)],
                'payment.p_should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'payment.p_should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'payment.p_remark'            => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('vi_additional_photos')
            + Uploader::validator_rule_upload_array('inspection_info.*.info_photos'),
            [],
            trans_property(VehicleInspection::class) + Arr::dot(['payment' => trans_property(Payment::class)])
        )->after(function (\Illuminate\Validation\Validator $validator) use ($vehicleInspection, $request, &$vehicle, &$saleContract) {
            if ($validator->failed()) {
                return;
            }
            if (null === $request->input('vi_id')) {
                // saleContract
                if ($sc_id = $request->input('sc_id')) {
                    /** @var SaleContract $saleContract */
                    $saleContract = SaleContract::query()->find($sc_id);
                    if (!$saleContract) {
                        $validator->errors()->add('vi_sc_id', 'The saleContract does not exist.');

                        return;
                    }

                    if (!$saleContract->check_status([ScStatus::SIGNED], $validator)) {
                        return;
                    }

                    switch ($request->input('inspection_type')) {
                        case ViInspectionType::SC_DISPATCH:
                            $vehicle          = $saleContract->Vehicle;
                            $VeStatusDispatch = VeStatusDispatch::NOT_DISPATCHED;

                            break;

                        case ViInspectionType::SC_RETURN:
                            $vehicle          = $saleContract->Vehicle;
                            $VeStatusDispatch = VeStatusDispatch::DISPATCHED;

                            break;

                        case ViInspectionType::VR_DISPATCH:
                            $vehicle          = $saleContract->VehicleReplace;
                            $VeStatusDispatch = VeStatusDispatch::NOT_DISPATCHED;

                            break;

                        case ViInspectionType::VR_RETURN:
                            $vehicle          = $saleContract->VehicleReplace;
                            $VeStatusDispatch = VeStatusDispatch::DISPATCHED;

                            break;
                    }
                    if (!$vehicle) {
                        $validator->errors()->add('vi_ve_id', '车辆不存在');

                        return;
                    }

                    $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [$VeStatusDispatch], $validator);
                    if (!$pass) {
                        return;
                    }
                }

                // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                if (null !== $vehicleInspection) { // 当是修改逻辑
                    $Payment = $vehicleInspection->Payment;
                    if ($Payment->exists && PPayStatus::PAID === $Payment->p_pay_status->value) {
                        if (!$request->boolean('add_should_pay')) {
                            $validator->errors()->add('Payment', '关联的支付已经支付，不能关闭财务记录。');

                            return;
                        }
                    }
                }
            }
        })
            ->validate()
        ;

        $input_payment = $input['payment'];

        if (null === $vehicleInspection) {
            $input['vi_ve_id'] = $vehicle['ve_id'];

            $input_payment['p_sc_id'] = $input['vi_sc_id'];
        }

        DB::transaction(function () use (&$input, &$input_payment, &$vehicleInspection, &$vehicle) {
            /** @var VehicleInspection $vehicleInspection */
            if (null === $vehicleInspection) {
                $vehicleInspection = VehicleInspection::query()->updateOrCreate(
                    array_intersect_key($input, array_flip(['vi_sc_id', 'vi_ve_id', 'vi_inspection_type', 'vi_inspection_datetime'])),
                    $input
                );

                switch ($vehicleInspection->vi_inspection_type) {
                    case ViInspectionType::SC_DISPATCH:
                    case ViInspectionType::VR_DISPATCH:
                        $vehicleInspection->Vehicle->updateStatus(
                            ve_status_dispatch: VeStatusDispatch::DISPATCHED
                        );

                        VehicleUsage::query()->updateOrCreate(
                            ['vu_sc_id' => $vehicleInspection->vi_sc_id, 'vu_ve_id' => $vehicleInspection->vi_ve_id, 'vu_start_vi_id' => $vehicleInspection->vi_id],
                            ['vu_end_vi_id' => null]
                        );

                        break;

                    case ViInspectionType::SC_RETURN:
                    case ViInspectionType::VR_RETURN:
                        $update_ve = $vehicleInspection->Vehicle->updateStatus(
                            ve_status_dispatch: VeStatusDispatch::NOT_DISPATCHED
                        );

                        $update_vu = VehicleUsage::query()->where(
                            ['vu_sc_id' => $vehicleInspection->vi_sc_id, 'vu_ve_id' => $vehicleInspection->vi_ve_id]
                        )
                            ->whereNull('vu_end_vi_id')
                            ->orderByDesc('vu_id')
                            ->first()
                            ?->update(['vu_end_vi_id' => $vehicleInspection->vi_id])
                        ;

                        break;

                    default:
                        break;
                }

                if ($vehicleInspection->vi_add_should_pay) {
                    $vehicleInspection->Payment()->create($input_payment);
                }
            } else {
                $vehicleInspection->update($input);

                if ($vehicleInspection->vi_add_should_pay) {
                    $Payment = $vehicleInspection->PaymentAll;
                    if ($Payment && $Payment->exists) {
                        if (PPayStatus::PAID === $Payment->p_pay_status->value) {
                            $Payment->fill($input_payment);
                            if ($Payment->isDirty()) {
                                throw new ClientException('财务信息已支付，不能做修改。'); // 不能修改财务记录的判断：修改状态 + 收款数据存在 + 收款记录为已支付 + 收款记录要做更新($model->isDirty()) =>
                            }
                        } else {
                            $Payment->update($input_payment + ['p_is_valid' => PIsValid::VALID]);
                        }
                    } else {
                        $vehicleInspection->Payment()->create($input_payment);
                    }
                } else {
                    $vehicleInspection->Payment()
                        ->where('p_pay_status', '=', PPayStatus::UNPAID)
                        ->update(['p_is_valid' => PIsValid::INVALID])
                    ;
                }
            }
        });

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleInspection $vehicleInspection): Response
    {
        $vehicleInspection->delete();

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function saleContractsOption(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            ['vi_inspection_type' => ['bail', 'required', 'string', Rule::in(ViInspectionType::label_keys())]]
        )
            ->validate()
        ;

        $this->response()->withExtras(
            match ($input['vi_inspection_type']) {
                ViInspectionType::SC_DISPATCH => SaleContract::options(
                    where: function (Builder $builder) {
                        $builder
                            ->whereIn('ve.ve_status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.ve_status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                            ->whereIn('sc.sc_status', [ScStatus::SIGNED])
                        ;
                    }
                ),
                ViInspectionType::SC_RETURN => SaleContract::options(
                    where: function (Builder $builder) {
                        $builder
                            ->whereIn('ve.ve_status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.ve_status_dispatch', [VeStatusDispatch::DISPATCHED])
                            ->whereIn('sc.sc_status', [ScStatus::SIGNED])
                        ;
                    }
                ),
                ViInspectionType::VR_DISPATCH => SaleContract::optionsVeReplace(
                    where: function (Builder $builder) {
                        $builder
//                            ->where('vr.change_start_date', '<=', $today = now()->format('Y-m-d'))
//                            ->where('vr.change_end_date', '>=', $today)
                            ->whereIn('ve.ve_status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.ve_status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                            ->whereIn('sc.sc_status', [ScStatus::SIGNED])
                        ;
                    }
                ),
                ViInspectionType::VR_RETURN => SaleContract::optionsVeReplace(
                    where: function (Builder $builder) {
                        $builder
//                            ->where('vr.change_start_date', '<=', $today = now()->format('Y-m-d'))
//                            ->where('vr.change_end_date', '>=', $today)
                            ->whereIn('ve.ve_status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.ve_status_dispatch', [VeStatusDispatch::DISPATCHED])
                            ->whereIn('sc.sc_status', [ScStatus::SIGNED])
                        ;
                    }
                ),
            }
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_inspection', ['vi_additional_photos', 'info_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? ViInspectionType::options_with_count(VehicleInspection::class) : ViInspectionType::options(),
            ViVehicleDamageStatus::options(),
            Exist::options(),
        );
    }
}
