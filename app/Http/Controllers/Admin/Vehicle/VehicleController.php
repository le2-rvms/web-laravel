<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\AdmTeamLimit;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VeType;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use App\Models\Admin\AdminTeam;
use App\Models\One\OneAccount;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleInspection;
use App\Models\Vehicle\VehicleManualViolation;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\VehicleRepair;
use App\Models\Vehicle\VehicleSchedule;
use App\Models\Vehicle\VehicleUsage;
use App\Models\Vehicle\VehicleViolation;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('车辆')]
class VehicleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VeType::labelOptions(),
            VeStatusService::labelOptions(),
            VeStatusRental::labelOptions(),
            VeStatusDispatch::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            VehicleModel::options(),
            Admin::options(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_vehicle_mgr);
            }),
            AdminTeam::options(),
            OneAccount::options(),
        );

        $query   = Vehicle::indexQuery();
        $columns = Vehicle::indexColumns();

        /** @var Admin $admin */
        $admin = auth()->user();

        if (($admin->team_limit->value ?? null) === AdmTeamLimit::LIMITED && $admin->team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('ve.ve_team_id', $admin->team_ids)->orwhereNull('ve.ve_team_id');
            });
        }

        // 如果是管理员和经理，则可以看到所有的车辆；如果不是管理员和经理，则只能看到车管为自己的车辆。
        $role_vehicle_manager = $admin->hasRole(AdminRole::role_vehicle_mgr);

        if ($role_vehicle_manager) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereNull('ve_vehicle_manager')->orWhere('ve_vehicle_manager', '=', $admin->id);
            });
        }

        $paginate = new PaginateService(
            [],
            [['ve.ve_id', 'desc']],
            ['kw', 've_vm_id', 've_status_service', 've_status_repair', 've_status_rental', 've_status_dispatch', 've_vehicle_manager'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('ve.ve_plate_no', 'like', '%'.$value.'%')
                            ->orWhere('ve.ve_license_owner', 'like', '%'.$value.'%')
                            ->orWhere('ve.ve_license_address', 'like', '%'.$value.'%')
                        ;
                    });
                },
            ],
            $columns
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(Vehicle $vehicle): Response
    {
        $this->response()->withExtras(
            VehicleInspection::kvList(ve_id: $vehicle->ve_id),
            VehicleUsage::kvList(ve_id: $vehicle->ve_id),
            VehicleRepair::kvList(ve_id: $vehicle->ve_id),
            VehicleViolation::kvList(ve_id: $vehicle->ve_id),
            VehicleManualViolation::kvList(ve_id: $vehicle->ve_id),
            VehicleSchedule::kvList(ve_id: $vehicle->ve_id),
        );

        return $this->response()->withData($vehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?Vehicle $vehicle): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_plate_no'                 => ['required', 'string', 'max:64', Rule::unique(Vehicle::class, 've_plate_no')->ignore($vehicle)],
                've_type'                     => ['nullable', Rule::in(VeType::label_keys())],
                've_vm_id'                    => ['nullable', 'integer', Rule::exists(VehicleModel::class, 'vm_id')],
                've_status_service'           => ['required', Rule::in(VeStatusService::label_keys())],
                've_status_rental'            => ['required', Rule::in(VeStatusRental::label_keys())],
                've_status_dispatch'          => ['required', Rule::in(VeStatusDispatch::label_keys())],
                've_vehicle_manager'          => ['nullable', Rule::exists(Admin::class, 'id')],
                've_team_id'                  => ['nullable', 'integer', Rule::exists(AdminTeam::class, 'at_id')],
                've_license_owner'            => ['nullable', 'string', 'max:100'],
                've_license_address'          => ['nullable', 'string', 'max:255'],
                've_license_usage'            => ['nullable', 'string', 'max:50'],
                've_license_type'             => ['nullable', 'string', 'max:50'],
                've_license_company'          => ['nullable', 'string', 'max:100'],
                've_license_vin_code'         => ['nullable', 'string', 'max:50'],
                've_license_engine_no'        => ['nullable', 'string', 'max:50'],
                've_license_purchase_date'    => ['nullable', 'date'],
                've_license_valid_until_date' => ['nullable', 'date', 'after:ve_license_purchase_date'],
                've_mileage'                  => ['nullable', 'integer'],
                've_color'                    => ['nullable', 'string', 'max:30'],
                've_cert_no'                  => ['nullable', 'string', 'max:50'],
                've_cert_valid_to'            => ['nullable', 'date'],
                've_remark'                   => ['nullable', 'string', 'max:255'],
                've_oa_id'                    => ['nullable', 'integer', Rule::exists(OneAccount::class, 'oa_id')],
            ]
            + Uploader::validator_rule_upload_object('ve_license_face_photo')
            + Uploader::validator_rule_upload_object('ve_license_back_photo')
            + Uploader::validator_rule_upload_object('ve_cert_photo')
            + Uploader::validator_rule_upload_array('ve_additional_photos'),
            [],
            trans_property(Vehicle::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle) {
                if ($validator->failed()) {
                    return;
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$vehicle) {
            if (null === $vehicle) {
                $vehicle = Vehicle::query()->create($input);
            } else {
                $vehicle->update($input);
            }
        });

        return $this->response()->withData($vehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(Vehicle $vehicle): Response
    {
        DB::transaction(function () use (&$vehicle) {
            $vehicle->delete();
        });

        return $this->response()->withData($vehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();

        $this->response()->withExtras(
            VehicleModel::options(),
            Admin::optionsWithRoles(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_vehicle_mgr);
            }),
            AdminTeam::options(),
            OneAccount::options(),
        );

        $vehicle = new Vehicle();

        return $this->response()->withData($vehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Vehicle $vehicle): Response
    {
        $this->options();

        $this->response()->withExtras(
            VehicleModel::options(),
            Admin::optionsWithRoles(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_vehicle_mgr);
            }),
            AdminTeam::options(),
            OneAccount::options(),
        );

        $this->response()->withExtras(
            VehicleInspection::kvList(ve_id: $vehicle->ve_id),
            VehicleUsage::kvList(ve_id: $vehicle->ve_id),
            VehicleRepair::kvList(ve_id: $vehicle->ve_id),
            VehicleViolation::kvList(ve_id: $vehicle->ve_id),
            VehicleManualViolation::kvList(ve_id: $vehicle->ve_id),
            VehicleSchedule::kvList(ve_id: $vehicle->ve_id),
        );

        return $this->response()->withData($vehicle)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload(
            $request,
            'vehicle',
            ['ve_license_face_photo', 've_license_back_photo', 've_cert_photo', 've_additional_photos'],
            $this
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VeType::options(),
            $with_group_count ? VeStatusService::options_with_count(Vehicle::class) : VeStatusService::options(),
            $with_group_count ? VeStatusRental::options_with_count(Vehicle::class) : VeStatusRental::options(),
            $with_group_count ? VeStatusDispatch::options_with_count(Vehicle::class) : VeStatusDispatch::options(),
        );
    }
}
