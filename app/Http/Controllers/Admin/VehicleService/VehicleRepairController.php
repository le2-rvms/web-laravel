<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\PIsValid;
use App\Enum\Payment\PPayStatus;
use App\Enum\Payment\PPtId;
use App\Enum\Vehicle\VcStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleRepair\VrCustodyVehicle;
use App\Enum\VehicleRepair\VrPickupStatus;
use App\Enum\VehicleRepair\VrRepairAttribute;
use App\Enum\VehicleRepair\VrRepairStatus;
use App\Enum\VehicleRepair\VrSettlementMethod;
use App\Enum\VehicleRepair\VrSettlementStatus;
use App\Exceptions\ClientException;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentAccount;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleCenter;
use App\Models\Vehicle\VehicleRepair;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('维修')]
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

        $query   = VehicleRepair::indexQuery();
        $columns = VehicleRepair::indexColumns();

        if ($admin->hasRole(AdminRole::role_vehicle_service)) {
            $vc_id_array = VehicleCenter::query()->whereJsonContains('vc_permitted', $admin->id)->pluck('vc_id')->toArray();

            // 维修厂角色仅能查看授权中心的维修记录。
            $query->whereIn('vc.vc_id', $vc_id_array);
        }

        $paginate = new PaginateService(
            [],
            [['vr.vr_id', 'desc']],
            ['kw', 'vr_ve_id', 'vr_entry_datetime', 'vr_repair_status', 'vr_vc_id'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.ve_plate_no', 'ilike', '%'.$value.'%')->orWhere('vr.vr_remark', 'ilike', '%'.$value.'%');
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

        $vehicleRepair = new VehicleRepair([
            'vr_entry_datetime' => now(),
            'vr_repair_info'    => [],
        ]);

        $vehicleRepair->load('Payment');

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleRepair $vehicleRepair): Response
    {
        $this->options();

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleRepair $vehicleRepair): Response
    {
        // 如果是维修厂，则只能看到自己的。
        /** @var Admin $admin */
        $admin = auth()->user();

        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
            VrRepairStatus::finalValue(),
            VehicleCenter::options(function (Builder $query) use ($admin) {
                if ($admin->hasRole(AdminRole::role_vehicle_service)) {
                    $vc_id_array = VehicleCenter::query()->whereJsonContains('vc_permitted', $admin->id)->pluck('vc_id')->toArray();

                    $query->whereIn('vc.vc_id', $vc_id_array);
                }
            }),
            PPayStatus::options(),
            PaymentAccount::options(),
        );

        if ($vehicleRepair->vr_ve_id) {
            $this->response()->withExtras(
                SaleContract::options(
                    function (Builder $builder) use ($vehicleRepair) {
                        $builder->where('sc.sc_ve_id', '=', $vehicleRepair->vr_ve_id);
                    }
                ),
            );
        }

        $vehicleRepair->load('Vehicle', 'SaleContract', 'SaleContract.Customer', 'Payment', 'Payment.PaymentType', 'Payment.PaymentAccount');

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleRepair $vehicleRepair = null): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'vr_ve_id'              => ['bail', 'required', 'integer'],
                'vr_sc_id'              => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'vr_entry_datetime'     => ['bail', 'required', 'date'],
                'vr_mileage'            => ['bail', 'nullable', 'integer', 'min:0'],
                'vr_repair_cost'        => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'vr_delay_days'         => ['bail', 'nullable', 'integer', 'min:0'],
                'vr_vc_id'              => ['bail', 'required', 'integer', 'min:1', Rule::exists(VehicleCenter::class, 'vc_id')->where('vc_status', VcStatus::ENABLED)],
                'vr_repair_content'     => ['bail', 'required', 'string'],
                'vr_departure_datetime' => ['bail', 'nullable', 'date'],
                'vr_repair_status'      => ['bail', 'required', 'string', Rule::in(VrRepairStatus::label_keys())],
                'vr_settlement_status'  => ['bail', 'required', 'string', Rule::in(VrSettlementStatus::label_keys())],
                'vr_pickup_status'      => ['bail', 'required', 'string', Rule::in(VrPickupStatus::label_keys())],
                'vr_settlement_method'  => ['bail', 'required', 'string', Rule::in(VrSettlementMethod::label_keys())],
                'vr_custody_vehicle'    => ['bail', 'required', 'string', Rule::in(VrCustodyVehicle::label_keys())],
                'vr_repair_attribute'   => ['bail', 'required', 'string', Rule::in(VrRepairAttribute::label_keys())],
                'vr_remark'             => ['bail', 'nullable', 'string'],
                'add_should_pay'        => ['bail', 'nullable', 'boolean'],

                'vr_repair_info'                 => ['bail', 'nullable', 'array'],
                'vr_repair_info.*.description'   => ['bail', 'nullable', 'string'],
                'vr_repair_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'vr_repair_info.*.part_cost'     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'vr_repair_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],

                'payment.p_pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(PPtId::REPAIR_FEE)],
                'payment.p_should_pay_date'   => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'date'],
                'payment.p_should_pay_amount' => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'numeric'],
                'payment.p_remark'            => ['bail', 'nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('vr_additional_photos')
            + Uploader::validator_rule_upload_array('vr_repair_info.*.info_photos'),
            [],
            trans_property(VehicleRepair::class) + Arr::dot(['payment' => trans_property(Payment::class)])
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($vehicleRepair, $request, &$vehicle) {
                if ($validator->failed()) {
                    return;
                }
                // ve_id
                $ve_id = $request->input('vr_ve_id');

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

                $sc_id = $request->input('vr_sc_id');

                if ($sc_id) {
                    // 需要收付时必须能关联到合同。
                    /** @var SaleContract $saleContract */
                    $saleContract = SaleContract::query()->find($sc_id);
                    if (!$saleContract) {
                        $validator->errors()->add('vr_sc_id', '合同不存在');

                        return;
                    }
                }

                // 不能关闭财务记录的判断：修改状态 + 收付款数据存在 + 收付款数据为已支付 + 当前是要关闭。
                if (null !== $vehicleRepair) { // 当是修改逻辑
                    $Payment = $vehicleRepair->Payment;
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

        $input_payment['p_sc_id'] = $input['vr_sc_id'];

        DB::transaction(function () use (&$input, &$input_payment, &$vehicle, &$vehicleRepair) {
            if (null === $vehicleRepair) {
                $vehicleRepair = VehicleRepair::query()->create($input);

                // 新建时按需生成应收款。
                if ($vehicleRepair->add_should_pay) {
                    $vehicleRepair->Payment()->create($input_payment);
                }
            } else {
                $vehicleRepair->update($input);

                if ($vehicleRepair->add_should_pay) {
                    $Payment = $vehicleRepair->Payment;
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
                        $vehicleRepair->Payment()->create($input_payment);
                    }
                } else {
                    // 关闭财务记录时，只作废未支付款项。
                    $vehicleRepair->Payment()->where('p_pay_status', '=', PPayStatus::UNPAID)->update(
                        [
                            'p_is_valid' => PIsValid::INVALID,
                        ]
                    );
                }
            }
        });

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleRepair $vehicleRepair): Response
    {
        $vehicleRepair->delete();

        return $this->response()->withData($vehicleRepair)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function saleContractsOption(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                've_id' => ['bail', 'required', 'integer'],
            ]
        )
            ->validate()
        ;

        $this->response()->withExtras(
            SaleContract::options(
                function (Builder $builder) use ($input) {
                    $builder->where('sc.sc_ve_id', '=', $input['ve_id']);
                }
            )
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_repair', ['vr_additional_photos', 'info_photos'], $this);
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
