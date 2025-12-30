<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\Vehicle\VcStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleMaintenance\VmCustodyVehicle;
use App\Enum\VehicleMaintenance\VmPickupStatus;
use App\Enum\VehicleMaintenance\VmSettlementMethod;
use App\Enum\VehicleMaintenance\VmSettlementStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleCenter;
use App\Models\Vehicle\VehicleMaintenance;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('保养')]
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

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        // 如果是维修厂，则只能看到自己的。
        /** @var Admin $admin */
        $admin = auth()->user();

        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
            VehicleCenter::options(function (Builder $query) use ($admin) {
                if ($admin->hasRole(AdminRole::role_vehicle_service)) {
                    $vc_id_array = VehicleCenter::query()->whereJsonContains('vc_permitted', $admin->id)->pluck('vc_id')->toArray();

                    $query->whereIn('vc.vc_id', $vc_id_array);
                }
            }),
        );

        $query   = VehicleMaintenance::indexQuery();
        $columns = VehicleMaintenance::indexColumns();

        if ($admin->hasRole(AdminRole::role_vehicle_service)) {
            $vc_id_array = VehicleCenter::query()->whereJsonContains('vc_permitted', $admin->id)->pluck('vc_id')->toArray();

            // 维修厂角色只看自己被授权的中心记录。
            $query->whereIn('vm.vm_vc_id', $vc_id_array);
        }

        $paginate = new PaginateService(
            [],
            [['vm.vm_id', 'desc']],
            ['kw', 'vm_ve_id', 'vm_entry_datetime', 'vm_vc_id'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.ve_plate_no', 'like', '%'.$value.'%')->orWhere('vm.vm_remark', 'like', '%'.$value.'%');
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
        // 如果是维修厂，则只能看到自己的。
        /** @var Admin $admin */
        $admin = auth()->user();

        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
            VehicleCenter::options(function (Builder $query) use ($admin) {
                if ($admin->hasRole(AdminRole::role_vehicle_service)) {
                    $vc_id_array = VehicleCenter::query()->whereJsonContains('vc_permitted', $admin->id)->pluck('vc_id')->toArray();

                    $query->whereIn('vc.vc_id', $vc_id_array);
                }
            }),
            PPayStatus::options(),
            PaymentAccount::options(),
        );

        $vehicleMaintenance = new VehicleMaintenance([
            'vm_entry_datetime' => now()->format('Y-m-d H:i'),
            //            'departure_datetime'    => now()->format('Y-m-d'),
            'vm_next_maintenance_date' => now()->addYear(),
            'vm_maintenance_mileage'   => 10000,
            'vm_maintenance_info'      => [],
        ]);

        $vehicleMaintenance->load('Payment');

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleMaintenance $vehicleMaintenance): Response
    {
        $this->options();

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleMaintenance $vehicleMaintenance): Response
    {
        // 如果是维修厂，则只能看到自己的。
        /** @var Admin $admin */
        $admin = auth()->user();

        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
            VehicleCenter::options(function (Builder $query) use ($admin) {
                if ($admin->hasRole(AdminRole::role_vehicle_service)) {
                    $vc_id_array = VehicleCenter::query()->whereJsonContains('vc_permitted', $admin->id)->pluck('vc_id')->toArray();

                    $query->whereIn('vc.vc_id', $vc_id_array);
                }
            }),
            PPayStatus::options(),
            PaymentAccount::options(),
        );

        if ($vehicleMaintenance->vm_ve_id) {
            $this->response()->withExtras(
                SaleContract::options(
                    where: function (Builder $builder) use ($vehicleMaintenance) {
                        $builder->where('sc.sc_ve_id', '=', $vehicleMaintenance->vm_ve_id);
                    }
                ),
            );
        }

        $vehicleMaintenance->load('Vehicle', 'SaleContract', 'SaleContract.Customer', 'Payment', 'Payment.PaymentType', 'Payment.PaymentAccount');

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleMaintenance $vehicleMaintenance = null): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'vm_ve_id'               => ['bail', 'required', 'integer'],
                'vm_sc_id'               => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'vm_entry_datetime'      => ['bail', 'required', 'date'],
                'vm_entry_mileage'       => ['bail', 'nullable', 'integer', 'min:0'],
                'vm_maintenance_mileage' => ['bail', 'nullable', 'integer', 'min:0'],
                'vm_maintenance_amount'  => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'vm_vc_id'               => ['bail', 'required', 'integer', 'min:1', Rule::exists(VehicleCenter::class, 'vc_id')->where('vc_status', VcStatus::ENABLED)],
                'vm_departure_datetime'  => ['bail', 'nullable', 'date'],
                'vm_settlement_status'   => ['bail', 'required', 'string', Rule::in(VmSettlementStatus::label_keys())],
                'vm_pickup_status'       => ['bail', 'required', 'string', Rule::in(VmPickupStatus::label_keys())],
                'vm_settlement_method'   => ['bail', 'required', 'string', Rule::in(VmSettlementMethod::label_keys())],
                'vm_custody_vehicle'     => ['bail', 'required', 'string', Rule::in(VmCustodyVehicle::label_keys())],
                'vm_remark'              => ['bail', 'nullable', 'string'],

                'vm_maintenance_info'                 => ['bail', 'sometimes', 'array'],
                'vm_maintenance_info.*.description'   => ['bail', 'nullable', 'string'],
                'vm_maintenance_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'vm_maintenance_info.*.part_cost'     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'vm_maintenance_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],

                'add_should_pay'              => ['bail', 'nullable', 'boolean'],
                'payment.p_pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(PPtId::MAINTENANCE_FEE)],
                'payment.p_should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'payment.p_should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'payment.p_remark'            => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('vm_additional_photos')
            + Uploader::validator_rule_upload_array('vm_maintenance_info.*.info_photos'),
            [],
            trans_property(VehicleMaintenance::class) + Arr::dot(['payment' => trans_property(Payment::class)])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($vehicleMaintenance, $request, &$vehicle) {
                if ($validator->failed()) {
                    return;
                }
                // ve_id
                $ve_id = $request->input('vm_ve_id');

                /** @var Vehicle $vehicle */
                $vehicle = Vehicle::query()->find($ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('ve_id', '车辆不存在');

                    return;
                }

                $pass = $vehicle->check_status(VeStatusService::YES, [], [], $validator);
                if (!$pass) {
                    return;
                }

                $sc_id = $request->input('vm_sc_id');

                if ($sc_id) {
                    // 需要收付时必须能关联到有效合同。
                    /** @var SaleContract $saleContract */
                    $saleContract = SaleContract::query()->find($sc_id);
                    if (!$saleContract) {
                        $validator->errors()->add('vm_sc_id', '合同不存在');

                        return;
                    }
                }

                // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                if (null !== $vehicleMaintenance) { // 当是修改逻辑
                    $Payment = $vehicleMaintenance->Payment;
                    if ($Payment->exists && PPayStatus::PAID === $Payment->p_pay_status->value) {
                        if (!$request->boolean('add_should_pay')) {
                            $validator->errors()->add('Payment', '关联的支付已经支付，不能关闭财务记录。');

                            return;
                        }
                    }
                }
            })
            ->validate()
        ;

        $input_payment = $input['payment'];

        $input_payment['p_sc_id'] = $input['vm_sc_id'];

        DB::transaction(function () use (&$input, &$input_payment, &$vehicle, &$vehicleMaintenance) {
            if (null === $vehicleMaintenance) {
                $vehicleMaintenance = VehicleMaintenance::query()->create($input);

                // 新建时按需生成应收款。
                if ($vehicleMaintenance->add_should_pay) {
                    $vehicleMaintenance->Payment()->create($input_payment);
                }
            } else {
                $vehicleMaintenance->update($input);

                if ($vehicleMaintenance->add_should_pay) {
                    $Payment = $vehicleMaintenance->Payment;
                    if ($Payment && $Payment->exists) {
                        if (PPayStatus::PAID === $Payment->p_pay_status->value) {
                            // 已支付的财务记录仅允许无变更提交。
                            $Payment->fill($input_payment);
                            if ($Payment->isDirty()) {
                                throw new ClientException('财务信息已支付，不能做修改。'); // 不能修改财务记录的判断：修改状态 + 收款数据存在 + 收款记录为已支付 + 收款记录要做更新($model->isDirty()) =>
                            }
                        } else {
                            $Payment->update($input_payment + ['p_is_valid' => PIsValid::VALID]);
                        }
                    } else {
                        $vehicleMaintenance->Payment()->create($input_payment);
                    }
                } else {
                    // 关闭财务记录时，只作废未支付款项。
                    $vehicleMaintenance->Payment()->where('p_pay_status', '=', PPayStatus::UNPAID)->update(
                        [
                            'p_is_valid' => PIsValid::INVALID,
                        ]
                    );
                }
            }
        });

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleMaintenance $vehicleMaintenance): Response
    {
        $vehicleMaintenance->delete();

        return $this->response()->withData($vehicleMaintenance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function saleContractsOption(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                've_id' => ['bail', 'required', 'integer'],
            ]
        )->validate();

        $this->response()->withExtras(
            SaleContract::options(
                where: function (Builder $builder) use ($input) {
                    $builder->where('sc.sc_ve_id', '=', $input['ve_id']);
                }
            )
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_maintenance', ['vm_additional_photos', 'info_photos'], $this);
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
