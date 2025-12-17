<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleInspection\ViIsCompanyBorne;
use App\Http\Controllers\Controller;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleInsurance;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('待续保')]
class VehicleInsuranceController extends Controller
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
        );

        $query = VehicleInsurance::indexQuery();

        $paginate = new PaginateService(
            [],
            [],
            ['kw', 'vi_ve_id'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('ve.ve_plate_no', 'like', '%'.$value.'%');
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $vehicleInsurance = new VehicleInsurance();

        return $this->response()->withData($vehicleInsurance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleInsurance $vehicleInsurance): Response
    {
        $this->options();

        return $this->response()->withData($vehicleInsurance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleInsurance $vehicleInsurance): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $vehicleInsurance->load('Vehicle');

        return $this->response()->withData($vehicleInsurance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleInsurance $vehicleInsurance = null): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'vi_ve_id' => ['required', 'integer'],
                // 交强险字段
                'vi_compulsory_plate_no'          => ['nullable', 'string', 'max:50'],
                'vi_compulsory_policy_number'     => ['nullable', 'string', 'max:50'],
                'vi_compulsory_start_date'        => ['nullable', 'date'],
                'vi_compulsory_end_date'          => ['nullable', 'date', 'after:compulsory_start_date'],
                'vi_compulsory_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
                'vi_compulsory_insured_company'   => ['nullable', 'string', 'max:255'],
                'vi_compulsory_org_code'          => ['nullable', 'string', 'max:50'],
                'vi_compulsory_insurance_company' => ['nullable', 'string', 'max:255'],
                // 承运人责任险字段
                'vi_carrier_liability_plate_no'          => ['nullable', 'string', 'max:50'],
                'vi_carrier_liability_policy_number'     => ['nullable', 'string', 'max:50'],
                'vi_carrier_liability_start_date'        => ['nullable', 'date'],
                'vi_carrier_liability_end_date'          => ['nullable', 'date', 'after:carrier_liability_start_date'],
                'vi_carrier_liability_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
                'vi_carrier_liability_insured_company'   => ['nullable', 'string', 'max:255'],
                'vi_carrier_liability_org_code'          => ['nullable', 'string', 'max:50'],
                'vi_carrier_liability_insurance_company' => ['nullable', 'string', 'max:255'],
                // 商业险字段
                'vi_commercial_plate_no'          => ['nullable', 'string', 'max:50'],
                'vi_commercial_policy_number'     => ['nullable', 'string', 'max:50'],
                'vi_commercial_start_date'        => ['nullable', 'date'],
                'vi_commercial_end_date'          => ['nullable', 'date', 'after:commercial_start_date'],
                'vi_commercial_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
                'vi_commercial_insured_company'   => ['nullable', 'string', 'max:255'],
                'vi_commercial_org_code'          => ['nullable', 'string', 'max:50'],
                'vi_commercial_insurance_company' => ['nullable', 'string', 'max:255'],

                // 其他字段
                'vi_is_company_borne' => ['sometimes', 'boolean'],
                'vi_remark'           => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_object('vi_compulsory_policy_file')
            + Uploader::validator_rule_upload_array('vi_compulsory_policy_photos')
            + Uploader::validator_rule_upload_object('vi_compulsory_policy_addendum_file')
            + Uploader::validator_rule_upload_object('vi_carrier_liability_policy_file')
            + Uploader::validator_rule_upload_array('vi_carrier_liability_policy_photos')
            + Uploader::validator_rule_upload_object('vi_commercial_policy_file')
            + Uploader::validator_rule_upload_array('vi_commercial_policy_photos')
            + Uploader::validator_rule_upload_object('vi_commercial_policy_addendum_file'),
            [],
            trans_property(VehicleInsurance::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle) {
                if ($validator->failed()) {
                    return;
                }
                // ve_id
                $ve_id = $request->input('vi_ve_id');

                /** @var Vehicle $vehicle */
                $vehicle = Vehicle::query()->find($ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('vi_ve_id', 'The vehicle does not exist.');

                    return;
                }

                $pass = $vehicle->check_status(VeStatusService::YES, [], [], $validator);
                if (!$pass) {
                    return;
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$vehicle, &$vehicleInsurance) {
            if (null === $vehicleInsurance) {
                $vehicleInsurance = VehicleInsurance::query()->create($input);
            } else {
                $vehicleInsurance->update($input);
            }
        });

        $vehicleInsurance->refresh();

        return $this->response()->withData($vehicleInsurance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleInsurance $vehicleInsurance): Response
    {
        $validator = Validator::make(
            [],
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if ($validator->failed()) {
                    return;
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $vehicleInsurance->delete();

        return $this->response()->withData($vehicleInsurance)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'vehicle_insurance',
            [
                'vi_compulsory_policy_file', 'vi_compulsory_policy_photos', 'vi_compulsory_policy_addendum_file',
                'vi_carrier_liability_policy_file', 'vi_carrier_liability_policy_photos',
                'vi_commercial_policy_file', 'vi_commercial_policy_photos', 'vi_commercial_policy_addendum_file',
            ],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            ViIsCompanyBorne::options(),
            VeStatusRental::options(),
        );
    }
}
