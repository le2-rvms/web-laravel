<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Vehicle\ScScStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VrCustodyVehicle;
use App\Enum\Vehicle\VrPickupStatus;
use App\Enum\Vehicle\VrRepairAttribute;
use App\Enum\Vehicle\VrRepairStatus;
use App\Enum\Vehicle\VrSettlementMethod;
use App\Enum\Vehicle\VrSettlementStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\SaleOrder;
use App\Models\Vehicle\ServiceCenter;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleRepair;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('维修管理')]
class VehicleRepairController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VrRepairAttribute::labelOptions(),
            VrCustodyVehicle::labelOptions(),
            VrPickupStatus::labelOptions(),
            VrSettlementMethod::labelOptions(),
            VrSettlementStatus::labelOptions(),
            VrRepairStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $query   = VehicleRepair::indexQuery();
        $columns = VehicleRepair::indexColumns();

        $paginate = new PaginateService(
            [],
            [['vr.vr_id', 'desc']],
            ['kw', 'vr_ve_id', 'vr_entry_datetime', 'vr_repair_status'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('vr.vr_remark', 'like', '%'.$value.'%')
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
            Vehicle::options(),
            ServiceCenter::options(),
            RpPayStatus::options(),
            PaymentAccount::options(),
        );

        $vehicleRepair = new VehicleRepair([
            'entry_datetime' => now(),
            'repair_info'    => [],
        ]);

        $vehicleRepair->load('Payment');

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(VehicleRepair $vehicleRepair): Response
    {
        $this->options();

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(VehicleRepair $vehicleRepair): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
            VrRepairStatus::finalValue(),
            ServiceCenter::options(),
            RpPayStatus::options(),
            PaymentAccount::options(),
        );

        if ($vehicleRepair->ve_id) {
            $this->response()->withExtras(
                SaleOrder::options(
                    where: function (Builder $builder) use ($vehicleRepair) {
                        $builder->where('so.ve_id', '=', $vehicleRepair->ve_id);
                    }
                ),
            );
        }

        $vehicleRepair->load('Vehicle', 'SaleOrder', 'SaleOrder.Customer', 'Payment', 'Payment.PaymentType', 'Payment.PaymentAccount');

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?VehicleRepair $vehicleRepair = null): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id'              => ['bail', 'required', 'integer'],
                'so_id'              => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'entry_datetime'     => ['bail', 'required', 'date'],
                'vr_mileage'         => ['bail', 'nullable', 'integer', 'min:0'],
                'repair_cost'        => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'delay_days'         => ['bail', 'nullable', 'integer', 'min:0'],
                'sc_id'              => ['bail', 'required', 'integer', 'min:1', Rule::exists(ServiceCenter::class)->where('status', ScScStatus::ENABLED)],
                'repair_content'     => ['bail', 'required', 'string'],
                'departure_datetime' => ['bail', 'nullable', 'date'],
                'repair_status'      => ['bail', 'required', 'string', Rule::in(VrRepairStatus::label_keys())],
                'settlement_status'  => ['bail', 'required', 'string', Rule::in(VrSettlementStatus::label_keys())],
                'pickup_status'      => ['bail', 'required', 'string', Rule::in(VrPickupStatus::label_keys())],
                'settlement_method'  => ['bail', 'required', 'string', Rule::in(VrSettlementMethod::label_keys())],
                'custody_vehicle'    => ['bail', 'required', 'string', Rule::in(VrCustodyVehicle::label_keys())],
                'repair_attribute'   => ['bail', 'required', 'string', Rule::in(VrRepairAttribute::label_keys())],
                'vr_remark'          => ['bail', 'nullable', 'string'],
                'add_should_pay'     => ['bail', 'sometimes', 'boolean'],

                'repair_info'                 => ['bail', 'nullable', 'array'],
                'repair_info.*.description'   => ['bail', 'nullable', 'string'],
                'repair_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'repair_info.*.part_cost'     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'repair_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],

                'payment.pt_id'             => 'bail', [Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(RpPtId::REPAIR_FEE)],
                'payment.should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'payment.should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'payment.rp_remark'         => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_array('repair_info.*.info_photos'),
            [],
            trans_property(VehicleRepair::class) + Arr::dot(['payment' => trans_property(Payment::class)])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($vehicleRepair, $request, &$vehicle) {
                if (!$validator->failed()) {
                    $so_id = $request->input('so_id');

                    if ($so_id) {
                        /** @var SaleOrder $saleOrder */
                        $SaleOrder = SaleOrder::query()->find($so_id);
                        if (!$SaleOrder) {
                            $validator->errors()->add('so_id', 'The sale_order does not exist.');

                            return;
                        }
                    }

                    // ve_id
                    $ve_id = $request->input('ve_id');

                    /** @var Vehicle $vehicle */
                    $vehicle = Vehicle::query()->find($ve_id);
                    if (!$vehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $vehicle->check_status(VeStatusService::YES, [], [], $validator);
                    if (!$pass) {
                        return;
                    }

                    // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                    if (null !== $vehicleRepair) { // 当是修改逻辑
                        $Payment = $vehicleRepair->Payment;
                        if ($Payment->exists && RpPayStatus::PAID === $Payment->pay_status->value) {
                            if (!$request->boolean('add_should_pay')) {
                                $validator->errors()->add('Payment', '关联的支付已经支付，不能关闭财务记录。');
                            }
                        }
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $input['payment']['so_id'] = $input['so_id'];

        DB::transaction(function () use (&$input, &$vehicle, &$vehicleRepair) {
            if (null === $vehicleRepair) {
                $vehicleRepair = VehicleRepair::query()->create($input);

                if ($vehicleRepair->add_should_pay) {
                    $vehicleRepair->Payment()->create($input['payment']);
                }
            } else {
                $vehicleRepair->update($input);

                if ($vehicleRepair->add_should_pay) {
                    $Payment = $vehicleRepair->Payment;
                    if ($Payment->exists) {
                        if (RpPayStatus::PAID === $Payment->pay_status->value) {
                            $Payment->fill($input['payment']);
                            if ($Payment->isDirty()) {
                                throw new ClientException('财务信息已支付，不能做修改。'); // 不能修改财务记录的判断：修改状态 + 收款数据存在 + 收款记录为已支付 + 收款记录要做更新($model->isDirty()) =>
                            }
                        } else {
                            $Payment->update($input['payment']);
                        }
                    } else {
                        $vehicleRepair->Payment()->create($input['payment']);
                    }
                } else {
                    $vehicleRepair->Payment()->where('pay_status', '=', RpPayStatus::UNPAID)->update(
                        [
                            'is_valid' => RpIsValid::INVALID,
                        ]
                    );
                }
            }
        });

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(VehicleRepair $vehicleRepair): Response
    {
        $validator = Validator::make(
            [],
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $vehicleRepair->delete();

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function saleOrdersOption(Request $request): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id' => ['bail', 'required', 'integer'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        $this->response()->withExtras(
            SaleOrder::options(
                where: function (Builder $builder) use ($input) {
                    $builder->where('so.ve_id', '=', $input['ve_id']);
                }
            )
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    #[PermissionAction(PermissionAction::EDIT)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_repair', ['additional_photos', 'info_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VrRepairAttribute::options(),
            VrCustodyVehicle::options(),
            VrPickupStatus::options(),
            VrSettlementMethod::options(),
            VrSettlementStatus::options(),
            $with_group_count ? VrRepairStatus::options_with_count(VehicleRepair::class) : VrRepairStatus::options(),
        );
    }
}
