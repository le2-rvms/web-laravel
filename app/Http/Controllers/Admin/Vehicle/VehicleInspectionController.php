<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Exist;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Sale\DtDtExportType;
use App\Enum\Sale\DtDtStatus;
use App\Enum\Sale\DtDtType;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\ViInspectionType;
use App\Enum\Vehicle\ViVehicleDamageStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Admin\Staff;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\DocTpl;
use App\Models\Sale\SaleOrder;
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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('验车管理')]
class VehicleInspectionController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            ViInspectionType::labelOptions(),
            ViVehicleDamageStatus::labelOptions(),
            Exist::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $query   = VehicleInspection::indexQuery();
        $columns = VehicleInspection::indexColumns();

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
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('vi.vi_remark', 'like', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras(
            RpPayStatus::options(),
            PaymentAccount::options(),
            Staff::optionsWithRoles(),
        );

        $vehicleInspection = new VehicleInspection([
            'inspection_info' => [],
            'processed_by'    => Auth::id(),
        ]);

        $vehicleInspection->load('Payment');

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(VehicleInspection $vehicleInspection): Response
    {
        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(VehicleInspection $vehicleInspection): Response
    {
        $this->options();
        $this->response()->withExtras(
            DocTpl::options(function (Builder $query) {
                $query->where('dt.dt_type', '=', DtDtType::VEHICLE_INSPECTION);
            }),
            RpPayStatus::options(),
            PaymentAccount::options(),
            Staff::optionsWithRoles(),
        );

        $vehicleInspection->load('Vehicle', 'SaleOrder', 'SaleOrder.Customer', 'SaleOrder.Vehicle', 'Payment', 'Payment.PaymentAccount');

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::DOC)]
    public function doc(Request $request, VehicleInspection $vehicleInspection, DocTplService $docTplService)
    {
        $input = $request->validate([
            'mode'  => ['required', Rule::in(DtDtExportType::label_keys())],
            'dt_id' => ['required', Rule::exists(DocTpl::class)->where('dt_type', DtDtType::VEHICLE_INSPECTION)->where('dt_status', DtDtStatus::ENABLED)],
        ]);

        $vehicleInspection->load('Vehicle', 'SaleOrder', 'SaleOrder.Customer');

        $docTpl = DocTpl::query()->find($input['dt_id']);

        $url = $docTplService->GenerateDoc($docTpl, $input['mode'], $vehicleInspection);

        return $this->response()->withData($url)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?VehicleInspection $vehicleInspection): Response
    {
        // 创建验证器实例
        $validator = Validator::make(
            $request->all(),
            [
                'so_id'                 => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'inspection_type'       => ['bail', 'required', 'string', Rule::in(ViInspectionType::label_keys())],
                'policy_copy'           => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'driving_license'       => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'operation_license'     => ['bail', 'nullable', Rule::in(Exist::label_keys())],
                'vehicle_damage_status' => ['bail', 'nullable', Rule::in(ViVehicleDamageStatus::label_keys())],
                'inspection_datetime'   => ['bail', 'required', 'date'],
                'vi_mileage'            => ['bail', 'required', 'integer', 'min:0'],
                'processed_by'          => ['bail', 'nullable', 'integer', Rule::exists(Staff::class, 'id')],
                'damage_deduction'      => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'vi_remark'             => ['bail', 'nullable', 'string'],
                'add_should_pay'        => ['bail', 'nullable', 'boolean'],

                'inspection_info'               => ['bail', 'nullable', 'array'],
                'inspection_info.*.description' => ['bail', 'nullable', 'string'],

                'payment.pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(RpPtId::VEHICLE_DAMAGE)],
                'payment.should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'payment.should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'payment.rp_remark'         => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_array('inspection_info.*.info_photos'),
            [],
            trans_property(VehicleInspection::class) + Arr::dot(['payment' => trans_property(Payment::class)])
        )->after(function (\Illuminate\Validation\Validator $validator) use ($vehicleInspection, $request, &$vehicle, &$customer) {
            if (!$validator->failed()) {
                if (null === $request->input('vi_id')) {
                    // sale_order
                    if ($so_id = $request->input('so_id')) {
                        /** @var SaleOrder $sale_order */
                        $sale_order = SaleOrder::query()->find($so_id);
                        if (!$sale_order) {
                            $validator->errors()->add('so_id', 'The sale_order does not exist.');

                            return;
                        }

                        if (!$sale_order->check_order_status([SoOrderStatus::SIGNED], $validator)) {
                            return;
                        }

                        /** @var Vehicle $vehicle */
                        $vehicle = $sale_order->Vehicle;
                        if (!$vehicle) {
                            $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                            return;
                        }

                        $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [ViInspectionType::DISPATCH == $request->input('inspection_type') ? VeStatusDispatch::NOT_DISPATCHED : VeStatusDispatch::DISPATCHED], $validator);
                        if (!$pass) {
                            return;
                        }
                    }

                    // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                    if (null !== $vehicleInspection) { // 当是修改逻辑
                        $Payment = $vehicleInspection->Payment;
                        if ($Payment->exists && RpPayStatus::PAID === $Payment->pay_status->value) {
                            if (!$request->boolean('add_should_pay')) {
                                $validator->errors()->add('Payment', '关联的支付已经支付，不能关闭财务记录。');
                            }
                        }
                    }
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input         = $validator->validated();
        $input_payment = $input['payment'];

        if ($input['so_id']) {
            $input_payment['so_id'] = $input['so_id'];
        }

        DB::transaction(function () use (&$input, &$input_payment, &$vehicleInspection, &$vehicle) {
            /** @var VehicleInspection $vehicleInspection */
            if (null === $vehicleInspection) {
                $input['ve_id'] = $vehicle['ve_id'];

                $vehicleInspection = VehicleInspection::query()->updateOrCreate(
                    array_intersect_key($input, array_flip(['so_id', 've_id', 'inspection_type', 'inspection_datetime'])),
                    $input
                );

                switch ($vehicleInspection->inspection_type) {
                    case ViInspectionType::DISPATCH:
                        $vehicleInspection->Vehicle->updateStatus(
                            status_dispatch: VeStatusDispatch::DISPATCHED
                        );

                        VehicleUsage::query()->updateOrCreate(
                            [
                                'so_id'       => $vehicleInspection->so_id,
                                've_id'       => $vehicleInspection->ve_id,
                                'start_vi_id' => $vehicleInspection->vi_id,
                            ],
                            [
                                'end_vi_id' => null,
                            ]
                        );

                        break;

                    case ViInspectionType::RETURN:
                        $update_ve = $vehicleInspection->Vehicle->updateStatus(
                            status_dispatch: VeStatusDispatch::NOT_DISPATCHED
                        );

                        $update_vu = VehicleUsage::query()->where(
                            [
                                'so_id' => $vehicleInspection->so_id,
                                've_id' => $vehicleInspection->ve_id,
                            ]
                        )
                            ->whereNull('end_vi_id')
                            ->orderByDesc('vu_id')
                            ->first()
                            ?->update([
                                'end_vi_id' => $vehicleInspection->vi_id,
                            ])
                        ;

                        break;

                    default:
                        break;
                }

                if ($vehicleInspection->add_should_pay) {
                    $vehicleInspection->Payment()->create($input_payment);
                }
            } else {
                $vehicleInspection->update($input);

                if ($vehicleInspection->add_should_pay) {
                    $Payment = $vehicleInspection->PaymentAll;
                    if ($Payment && $Payment->exists) {
                        if (RpPayStatus::PAID === $Payment->pay_status->value) {
                            $Payment->fill($input_payment);
                            if ($Payment->isDirty()) {
                                throw new ClientException('财务信息已支付，不能做修改。'); // 不能修改财务记录的判断：修改状态 + 收款数据存在 + 收款记录为已支付 + 收款记录要做更新($model->isDirty()) =>
                            }
                        } else {
                            $Payment->update($input_payment + ['is_valid' => RpIsValid::VALID]);
                        }
                    } else {
                        $vehicleInspection->Payment()->create($input_payment);
                    }
                } else {
                    $vehicleInspection->Payment()->where('pay_status', '=', RpPayStatus::UNPAID)->update(
                        [
                            'is_valid' => RpIsValid::INVALID,
                        ]
                    );
                }
            }
        });

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(VehicleInspection $vehicleInspection): Response
    {
        $vehicleInspection->delete();

        return $this->response()->withData($vehicleInspection)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function saleOrdersOption(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'inspection_type' => ['bail', 'required', 'string', Rule::in(ViInspectionType::label_keys())],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $this->response()->withExtras(
            match ($input['inspection_type']) {
                ViInspectionType::DISPATCH => SaleOrder::options(
                    where: function (Builder $builder) {
                        $builder->whereIn('ve.status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                            ->whereIn('so.order_status', [SoOrderStatus::SIGNED])
                        ;
                    }
                ),
                ViInspectionType::RETURN => SaleOrder::options(
                    where: function (Builder $builder) {
                        $builder->whereIn('ve.status_rental', [VeStatusRental::RENTED])
                            ->whereIn('ve.status_dispatch', [VeStatusDispatch::DISPATCHED])
                            ->whereIn('so.order_status', [SoOrderStatus::SIGNED])
                        ;
                    }
                ),
            }
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_inspection', ['additional_photos', 'info_photos'], $this);
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
