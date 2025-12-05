<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\ViIsCompanyBorne;
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
                    $builder->where('ve.plate_no', 'like', '%'.$value.'%');
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
                've_id' => ['required', 'integer'],
                // 交强险字段
                'compulsory_plate_no'          => ['nullable', 'string', 'max:50'],
                'compulsory_policy_number'     => ['nullable', 'string', 'max:50'],
                'compulsory_start_date'        => ['nullable', 'date'],
                'compulsory_end_date'          => ['nullable', 'date', 'after:compulsory_start_date'],
                'compulsory_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
                'compulsory_insured_company'   => ['nullable', 'string', 'max:255'],
                'compulsory_org_code'          => ['nullable', 'string', 'max:50'],
                'compulsory_insurance_company' => ['nullable', 'string', 'max:255'],
                // 承运人责任险字段
                'carrier_liability_plate_no'          => ['nullable', 'string', 'max:50'],
                'carrier_liability_policy_number'     => ['nullable', 'string', 'max:50'],
                'carrier_liability_start_date'        => ['nullable', 'date'],
                'carrier_liability_end_date'          => ['nullable', 'date', 'after:carrier_liability_start_date'],
                'carrier_liability_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
                'carrier_liability_insured_company'   => ['nullable', 'string', 'max:255'],
                'carrier_liability_org_code'          => ['nullable', 'string', 'max:50'],
                'carrier_liability_insurance_company' => ['nullable', 'string', 'max:255'],
                // 商业险字段
                'commercial_plate_no'          => ['nullable', 'string', 'max:50'],
                'commercial_policy_number'     => ['nullable', 'string', 'max:50'],
                'commercial_start_date'        => ['nullable', 'date'],
                'commercial_end_date'          => ['nullable', 'date', 'after:commercial_start_date'],
                'commercial_premium'           => ['nullable', 'decimal:0,2', 'gte:0'],
                'commercial_insured_company'   => ['nullable', 'string', 'max:255'],
                'commercial_org_code'          => ['nullable', 'string', 'max:50'],
                'commercial_insurance_company' => ['nullable', 'string', 'max:255'],

                // 其他字段
                'is_company_borne' => ['sometimes', 'boolean'],
                'vi_remark'        => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_object('compulsory_policy_file')
            + Uploader::validator_rule_upload_array('compulsory_policy_photos')
            + Uploader::validator_rule_upload_object('compulsory_policy_addendum_file')
            + Uploader::validator_rule_upload_object('carrier_liability_policy_file')
            + Uploader::validator_rule_upload_array('carrier_liability_policy_photos')
            + Uploader::validator_rule_upload_object('commercial_policy_file')
            + Uploader::validator_rule_upload_array('commercial_policy_photos')
            + Uploader::validator_rule_upload_object('commercial_policy_addendum_file'),
            [],
            trans_property(VehicleInsurance::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle) {
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
                if (!$validator->failed()) {
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
                'compulsory_policy_file', 'compulsory_policy_photos', 'compulsory_policy_addendum_file',
                'carrier_liability_policy_file', 'carrier_liability_policy_photos',
                'commercial_policy_file', 'commercial_policy_photos', 'commercial_policy_addendum_file',
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
