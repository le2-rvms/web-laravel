<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Console\Commands\Sys\ImportAdminAndRoles;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\YesNo;
use App\Http\Controllers\Controller;
use App\Models\Admin\Staff;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehiclePreparation;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('整备管理')]
class VehiclePreparationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::INDEX)]
    public function index(Request $request): Response
    {
        $this->response()->withExtras(
        );

        $query = VehiclePreparation::indexQuery();

        $paginate = new PaginateService(
            [],
            [['vp.vp_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function create(Request $request)
    {
        $this->response()->withExtras(
            YesNo::options(),
            Vehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::PENDING]);
                }
            )
        );

        /** @var Staff $user */
        $user = auth()->user();

        $has_role_prep_vehicle  = $user->hasRole(ImportAdminAndRoles::role_vehicle_mgr) || $user->hasRole(config('setting.super_role.name'));
        $has_role_prep_document = $user->hasRole(ImportAdminAndRoles::role_payment) || $user->hasRole(config('setting.super_role.name'));

        return $this->response()->withData([
            'role_prep_vehicle'  => $has_role_prep_vehicle,
            'role_prep_document' => $has_role_prep_document,
        ])->respond();
    }

    #[PermissionAction(PermissionAction::ADD)]
    public function store(Request $request)
    {
        $user = auth()->user();

        $role_prep_vehicle  = $user->hasRole(ImportAdminAndRoles::role_vehicle_mgr) || $user->hasRole(config('setting.super_role.name'));
        $role_prep_document = $user->hasRole(ImportAdminAndRoles::role_payment) || $user->hasRole(config('setting.super_role.name'));

        $validator = Validator::make(
            $request->all(),
            [
                've_id' => ['bail', 'required', 'integer'],
            ]
           + ($role_prep_vehicle ? [
               'vehicle_check_is' => ['required', Rule::in(YesNo::YES)],
           ] : [])
            + ($role_prep_document ? [
                'annual_check_is'   => ['required', Rule::in(YesNo::YES)],
                'insured_check_is'  => ['required', Rule::in(YesNo::YES)],
                'document_check_is' => ['required', Rule::in(YesNo::YES)],
            ] : []),
            [],
            trans_property(VehiclePreparation::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle, &$customer) {
                if (!$validator->failed()) {
                    // ve_id
                    $ve_id   = $request->input('ve_id');
                    $vehicle = Vehicle::query()->find($ve_id);
                    if (!$vehicle) {
                        $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                        return;
                    }

                    $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::PENDING], [VeStatusDispatch::NOT_DISPATCHED], $validator);
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

        if ($input['annual_check_is']) {
            $input['annual_check_dt'] = now();
        }
        if ($input['insured_check_is']) {
            $input['insured_check_dt'] = now();
        }
        if ($input['document_check_is']) {
            $input['document_check_dt'] = now();
        }
        if ($input['vehicle_check_is']) {
            $input['vehicle_check_dt'] = now();
        }

        DB::transaction(function () use (&$input, &$vehiclePreparation) {
            /** @var VehiclePreparation $vehiclePreparation */
            $vehiclePreparation = VehiclePreparation::query()
                ->where('ve_id', '=', $input['ve_id'])
                ->where(
                    function (Builder $query) {
                        $query->where('annual_check_is', '=', YesNo::NO)
                            ->orWhere('insured_check_is', '=', YesNo::NO)
                            ->orWhere('vehicle_check_is', '=', YesNo::NO)
                            ->orWhere('document_check_is', '=', YesNo::NO)
                        ;
                    }
                )->first()
            ;
            if (null === $vehiclePreparation) {
                $vehiclePreparation = VehiclePreparation::query()->create($input);
            } else {
                $vehiclePreparation->update($input);
            }

            if (YesNo::YES == $vehiclePreparation->annual_check_is
                && YesNo::YES == $vehiclePreparation->insured_check_is
                && YesNo::YES == $vehiclePreparation->vehicle_check_is
                && YesNo::YES == $vehiclePreparation->document_check_is) {
                $vehiclePreparation->Vehicle->updateStatus(status_rental: VeStatusRental::LISTED);
            }
        });

        return $this->response()->withData($vehiclePreparation)->respond();
    }

    public function show(VehiclePreparation $vehiclePreparation) {}

    public function edit(VehiclePreparation $vehiclePreparation) {}

    public function update(Request $request, VehiclePreparation $vehiclePreparation) {}

    public function destroy(VehiclePreparation $vehiclePreparation) {}

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
