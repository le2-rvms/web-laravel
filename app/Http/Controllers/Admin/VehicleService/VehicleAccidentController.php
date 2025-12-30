<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VcStatus;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleAccident\VaClaimStatus;
use App\Enum\VehicleAccident\VaManagedVehicle;
use App\Enum\VehicleAccident\VaPickupStatus;
use App\Enum\VehicleAccident\VaRepairStatus;
use App\Enum\VehicleAccident\VaSettlementMethod;
use App\Enum\VehicleAccident\VaSettlementStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleCenter;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('出险')]
class VehicleAccidentController extends Controller
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
            Vehicle::options(),
            Customer::options(),
        );

        $query = VehicleAccident::indexQuery();

        $columns = VehicleAccident::indexColumns();

        $paginate = new PaginateService(
            [],
            [['va.va_id', 'desc']],
            ['kw', 'va_ve_id', 'va_claim_status', 'va_accident_dt'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.ve_plate_no', 'like', '%'.$value.'%')->orWhere('va.va_accident_location', 'like', '%'.$value.'%')->orWhere('va.va_description', 'like', '%'.$value.'%')->orWhere('cu.cu_contact_name', 'like', '%'.$value.'%');
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
            Vehicle::options(),
            Customer::options(),
            VehicleCenter::options(),
        );

        $vehicleAccident = new VehicleAccident([
            'va_accident_dt'    => now(),
            'va_factory_in_dt'  => now(),
            'va_factory_out_dt' => now(),
            'va_accident_info'  => [],
        ]);

        return $this->response()->withData($vehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleAccident $vehicleAccident): Response
    {
        $this->options();

        return $this->response()->withData($vehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleAccident $vehicleAccident): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
            Customer::options(),
            VehicleCenter::options(),
        );

        if ($vehicleAccident->va_ve_id) {
            $this->response()->withExtras(
                SaleContract::options(
                    function (Builder $builder) use ($vehicleAccident) {
                        $builder->where('sc.sc_ve_id', '=', $vehicleAccident->va_ve_id);
                    }
                ),
            );
        }

        $vehicleAccident->load('Vehicle', 'SaleContract'); // ,'SaleContract.Customer'

        return $this->response()->withData($vehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleAccident $vehicleAccident = null): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'va_ve_id'                         => ['bail', 'required', 'integer'],
                'va_sc_id'                         => ['bail', 'nullable', 'integer'],
                'va_accident_location'             => ['bail', 'nullable', 'string', 'max:255'],
                'va_accident_dt'                   => ['bail', 'required', 'date'],
                'va_responsible_party'             => ['bail', 'nullable', 'string', 'max:255'],
                'va_claim_status'                  => ['bail', 'nullable', 'string', Rule::in(VaClaimStatus::label_keys())],
                'va_self_amount'                   => ['bail', 'nullable', 'numeric'],
                'va_third_party_amount'            => ['bail', 'nullable', 'numeric'],
                'va_insurance_company'             => ['bail', 'nullable', 'string', 'max:100'],
                'va_description'                   => ['bail', 'nullable', 'string'],
                'va_factory_in_dt'                 => ['bail', 'nullable', 'date'],
                'va_vc_id'                         => ['bail', 'required', 'integer', Rule::exists(VehicleCenter::class, 'vc_id')->where('vc_status', VcStatus::ENABLED)],
                'va_repair_content'                => ['bail', 'nullable', 'string'],
                'va_repair_status'                 => ['bail', 'nullable', 'string', Rule::in(VaRepairStatus::label_keys())],
                'va_factory_out_dt'                => ['bail', 'nullable', 'date'],
                'va_settlement_status'             => ['bail', 'nullable', 'string', Rule::in(VaSettlementStatus::label_keys())],
                'va_pickup_status'                 => ['bail', 'nullable', 'string', Rule::in(VaPickupStatus::label_keys())],
                'va_settlement_method'             => ['bail', 'nullable', 'string', Rule::in(VaSettlementMethod::label_keys())],
                'va_managed_vehicle'               => ['bail', 'nullable', 'string', Rule::in(VaManagedVehicle::label_keys())],
                'va_remark'                        => ['bail', 'nullable', 'string'],
                'va_additional_photos'             => ['bail', 'nullable', 'array'],
                'va_accident_info'                 => ['bail', 'nullable', 'array'],
                'va_accident_info.*.description'   => ['bail', 'nullable', 'string'],
                'va_accident_info.*.part_name'     => ['bail', 'nullable', 'string', 'max:255'],
                'va_accident_info.*.part_cost'     => ['bail', 'nullable', 'decimal:0,2', 'gte:0'],
                'va_accident_info.*.part_quantity' => ['bail', 'nullable', 'integer', 'min:1'],
            ]
            + Uploader::validator_rule_upload_array('va_additional_photos')
            + Uploader::validator_rule_upload_array('va_accident_info.*.info_photos'),
            [],
            trans_property(VehicleAccident::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle) {
                if ($validator->failed()) {
                    return;
                }
                // ve_id
                $ve_id = $request->input('va_ve_id');

                /** @var Vehicle $vehicle */
                $vehicle = Vehicle::query()->find($ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('va_ve_id', '车辆不存在');

                    return;
                }

                // 出险录入仅允许在役车辆。
                $pass = $vehicle->check_status(VeStatusService::YES, [], [], $validator);
                if (!$pass) {
                    return;
                }

                $sc_id = $request->input('va_sc_id');

                if ($sc_id) {
                    // 有合同号时必须能找到对应合同。
                    /** @var SaleContract $saleContract */
                    $saleContract = SaleContract::query()->find($sc_id);
                    if (!$saleContract) {
                        $validator->errors()->add('va_sc_id', '合同不存在');

                        return;
                    }
                }
            })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$vehicle, &$vehicleAccident) {
            if (null === $vehicleAccident) {
                $vehicleAccident = VehicleAccident::query()->create($input);
            } else {
                $vehicleAccident->update($input);
            }
        });

        return $this->response()->withData($vehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleAccident $vehicleAccident): Response
    {
        $vehicleAccident->delete();

        return $this->response()->withData($vehicleAccident)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_accident', ['va_additional_photos', 'info_photos'], $this);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function saleContractsOption(Request $request): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'va_ve_id' => ['bail', 'required', 'integer'],
            ]
        )
            ->validate()
        ;

        $this->response()->withExtras(
            SaleContract::options(
                function (Builder $builder) use ($input) {
                    $builder->where('sc.sc_ve_id', '=', $input['va_ve_id']);
                }
            )
        );

        return $this->response()->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VaClaimStatus::options_with_count(VehicleAccident::class) : VaClaimStatus::options(),
            $with_group_count ? VaRepairStatus::options_with_count(VehicleAccident::class) : VaRepairStatus::options(),
            VaSettlementStatus::options(),
            VaPickupStatus::options(),
            VaSettlementMethod::options(),
            VaManagedVehicle::options(),
        );
    }
}
