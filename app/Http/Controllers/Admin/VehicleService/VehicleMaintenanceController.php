<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Vehicle\ScScStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VmCustodyVehicle;
use App\Enum\Vehicle\VmPickupStatus;
use App\Enum\Vehicle\VmSettlementMethod;
use App\Enum\Vehicle\VmSettlementStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\SaleOrder;
use App\Models\Vehicle\ServiceCenter;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleMaintenance;
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

#[PermissionType('保养管理')]
class VehicleMaintenanceController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VmCustodyVehicle::labelOptions(),
            VmPickupStatus::labelOptions(),
            VmSettlementMethod::labelOptions(),
            VmSettlementStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $query   = VehicleMaintenance::indexQuery();
        $columns = VehicleMaintenance::indexColumns();

        $paginate = new PaginateService(
            [],
            [['vm.vm_id', 'desc']],
            ['kw', 'vm_ve_id', 'vm_entry_datetime'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')
                            ->orWhere('vm.vm_remark', 'like', '%'.$value.'%')
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

        $vehicleMaintenance = new VehicleMaintenance([
            //            'entry_datetime'        => now()->format('Y-m-d'),
            //            'departure_datetime'    => now()->format('Y-m-d'),
            'next_maintenance_date' => now()->addYear()->format('Y-m-d'),
            'maintenance_mileage'   => 10000,
            'maintenance_info'      => [],
        ]);

        $vehicleMaintenance->load('Payment');

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::SHOW)]
    public function show(VehicleMaintenance $vehicleMaintenance): Response
    {
        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function edit(VehicleMaintenance $vehicleMaintenance): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
            ServiceCenter::options(),
            RpPayStatus::options(),
            PaymentAccount::options(),
        );

        if ($vehicleMaintenance->ve_id) {
            $this->response()->withExtras(
                SaleOrder::options(
                    where: function (Builder $builder) use ($vehicleMaintenance) {
                        $builder->where('so.ve_id', '=', $vehicleMaintenance->ve_id);
                    }
                ),
            );
        }

        $vehicleMaintenance->load('Vehicle', 'SaleOrder', 'SaleOrder.Customer', 'Payment', 'Payment.PaymentType', 'Payment.PaymentAccount');

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::EDIT)]
    public function update(Request $request, ?VehicleMaintenance $vehicleMaintenance = null): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id'               => ['bail', 'required', 'integer'],
                'so_id'               => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'entry_datetime'      => ['bail', 'required', 'date'],
                'entry_mileage'       => ['bail', 'nullable', 'integer', 'min:0'],
                'maintenance_mileage' => ['bail', 'nullable', 'integer', 'min:0'],
                'maintenance_amount'  => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'sc_id'               => ['bail', 'required', 'integer', 'min:1', Rule::exists(ServiceCenter::class)->where('status', ScScStatus::ENABLED)],
                'departure_datetime'  => ['bail', 'nullable', 'date'],
                'settlement_status'   => ['bail', 'required', 'string', Rule::in(VmSettlementStatus::label_keys())],
                'pickup_status'       => ['bail', 'required', 'string', Rule::in(VmPickupStatus::label_keys())],
                'settlement_method'   => ['bail', 'required', 'string', Rule::in(VmSettlementMethod::label_keys())],
                'custody_vehicle'     => ['bail', 'required', 'string', Rule::in(VmCustodyVehicle::label_keys())],
                'vm_remark'           => ['bail', 'nullable', 'string'],

                'maintenance_info'                 => ['bail', 'sometimes', 'array'],
                'maintenance_info.*.description'   => ['bail', 'nullable', 'string'],
                'maintenance_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'maintenance_info.*.part_cost'     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'maintenance_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],

                'add_should_pay'            => ['bail', 'sometimes', 'boolean'],
                'payment.pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(RpPtId::MAINTENANCE_FEE)],
                'payment.should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'payment.should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'payment.rp_remark'         => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos')
            + Uploader::validator_rule_upload_array('maintenance_info.*.info_photos'),
            [],
            trans_property(VehicleMaintenance::class) + Arr::dot(['payment' => trans_property(Payment::class)])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($vehicleMaintenance, $request, &$vehicle) {
                if (!$validator->failed()) {
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

                    $so_id = $request->input('so_id');

                    if ($so_id) {
                        /** @var SaleOrder $saleOrder */
                        $SaleOrder = SaleOrder::query()->find($so_id);
                        if (!$SaleOrder) {
                            $validator->errors()->add('so_id', 'The sale_order does not exist.');

                            return;
                        }
                    }

                    // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                    if (null !== $vehicleMaintenance) { // 当是修改逻辑
                        $Payment = $vehicleMaintenance->Payment;
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

        DB::transaction(function () use (&$input, &$vehicle, &$vehicleMaintenance) {
            if (null === $vehicleMaintenance) {
                $vehicleMaintenance = VehicleMaintenance::query()->create($input);

                if ($vehicleMaintenance->add_should_pay) {
                    $vehicleMaintenance->Payment()->create($input['payment']);
                }
            } else {
                $vehicleMaintenance->update($input);

                if ($vehicleMaintenance->add_should_pay) {
                    $Payment = $vehicleMaintenance->Payment;
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
                        $vehicleMaintenance->Payment()->create($input['payment']);
                    }
                } else {
                    $vehicleMaintenance->Payment()->where('pay_status', '=', RpPayStatus::UNPAID)->update(
                        [
                            'is_valid' => RpIsValid::INVALID,
                        ]
                    );
                }
            }
        });

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::DELETE)]
    public function destroy(VehicleMaintenance $vehicleMaintenance): Response
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

        $vehicleMaintenance->delete();

        return $this->response()->withData($vehicleMaintenance)->respond();
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
        return Uploader::upload($request, 'vehicle_maintenance', ['additional_photos', 'info_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VmCustodyVehicle::options(),
            VmPickupStatus::options(),
            VmSettlementMethod::options(),
            VmSettlementStatus::options(),
        );
    }
}
