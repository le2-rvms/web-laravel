<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Payment\RpIsValid;
use App\Enum\Payment\RpPayStatus;
use App\Enum\Payment\RpPtId;
use App\Enum\Vehicle\VcVcStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VrCustodyVehicle;
use App\Enum\Vehicle\VrPickupStatus;
use App\Enum\Vehicle\VrRepairAttribute;
use App\Enum\Vehicle\VrRepairStatus;
use App\Enum\Vehicle\VrSettlementMethod;
use App\Enum\Vehicle\VrSettlementStatus;
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
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

                    $query->whereIn('vr.vc_id', $vc_id_array);
                }
            }),
        );

        $query   = VehicleRepair::indexQuery();
        $columns = VehicleRepair::indexColumns();

        if ($admin->hasRole(AdminRole::role_vehicle_service)) {
            $vc_id_array = VehicleCenter::query()->whereJsonContains('vc_permitted', $admin->id)->pluck('vc_id')->toArray();

            $query->whereIn('vr.vc_id', $vc_id_array);
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
                        $builder->where('ve.plate_no', 'like', '%'.$value.'%')->orWhere('vr.vr_remark', 'like', '%'.$value.'%');
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

                    $query->whereIn('vr.vc_id', $vc_id_array);
                }
            }),
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

                    $query->whereIn('vr.vc_id', $vc_id_array);
                }
            }),
            RpPayStatus::options(),
            PaymentAccount::options(),
        );

        if ($vehicleRepair->ve_id) {
            $this->response()->withExtras(
                SaleContract::options(
                    where: function (Builder $builder) use ($vehicleRepair) {
                        $builder->where('sc.ve_id', '=', $vehicleRepair->ve_id);
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
        $validator = Validator::make(
            $request->all(),
            [
                've_id'              => ['bail', 'required', 'integer'],
                'sc_id'              => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), 'nullable', 'integer'],
                'entry_datetime'     => ['bail', 'required', 'date'],
                'vr_mileage'         => ['bail', 'nullable', 'integer', 'min:0'],
                'repair_cost'        => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'delay_days'         => ['bail', 'nullable', 'integer', 'min:0'],
                'vc_id'              => ['bail', 'required', 'integer', 'min:1', Rule::exists(VehicleCenter::class)->where('vc_status', VcVcStatus::ENABLED)],
                'repair_content'     => ['bail', 'required', 'string'],
                'departure_datetime' => ['bail', 'nullable', 'date'],
                'repair_status'      => ['bail', 'required', 'string', Rule::in(VrRepairStatus::label_keys())],
                'settlement_status'  => ['bail', 'required', 'string', Rule::in(VrSettlementStatus::label_keys())],
                'pickup_status'      => ['bail', 'required', 'string', Rule::in(VrPickupStatus::label_keys())],
                'settlement_method'  => ['bail', 'required', 'string', Rule::in(VrSettlementMethod::label_keys())],
                'custody_vehicle'    => ['bail', 'required', 'string', Rule::in(VrCustodyVehicle::label_keys())],
                'repair_attribute'   => ['bail', 'required', 'string', Rule::in(VrRepairAttribute::label_keys())],
                'vr_remark'          => ['bail', 'nullable', 'string'],
                'add_should_pay'     => ['bail', 'nullable', 'boolean'],

                'repair_info'                 => ['bail', 'nullable', 'array'],
                'repair_info.*.description'   => ['bail', 'nullable', 'string'],
                'repair_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'repair_info.*.part_cost'     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'repair_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],

                'payment.pt_id'             => ['bail', Rule::requiredIf($request->boolean('add_should_pay')), Rule::in(RpPtId::REPAIR_FEE)],
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

                    $sc_id = $request->input('sc_id');

                    if ($sc_id) {
                        /** @var SaleContract $saleContract */
                        $saleContract = SaleContract::query()->find($sc_id);
                        if (!$saleContract) {
                            $validator->errors()->add('sc_id', 'The sale_contract does not exist.');

                            return;
                        }
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

        $input         = $validator->validated();
        $input_payment = $input['payment'];

        $input_payment['sc_id'] = $input['sc_id'];

        DB::transaction(function () use (&$input, &$input_payment, &$vehicle, &$vehicleRepair) {
            if (null === $vehicleRepair) {
                $vehicleRepair = VehicleRepair::query()->create($input);

                if ($vehicleRepair->add_should_pay) {
                    $vehicleRepair->Payment()->create($input_payment);
                }
            } else {
                $vehicleRepair->update($input);

                if ($vehicleRepair->add_should_pay) {
                    $Payment = $vehicleRepair->Payment;
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
                        $vehicleRepair->Payment()->create($input_payment);
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

    #[PermissionAction(PermissionAction::WRITE)]
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

    #[PermissionAction(PermissionAction::WRITE)]
    public function saleContractsOption(Request $request): Response
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
            SaleContract::options(
                where: function (Builder $builder) use ($input) {
                    $builder->where('sc.ve_id', '=', $input['ve_id']);
                }
            )
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
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
