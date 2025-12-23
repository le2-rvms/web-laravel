<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\YesNo;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehiclePreparation;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('整备')]
class VehiclePreparationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    #[PermissionAction(PermissionAction::READ)]
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

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request)
    {
        $this->response()->withExtras(
            YesNo::options(),
            Vehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('ve_status_rental', [VeStatusRental::PENDING]);
                }
            )
        );

        /** @var Admin $admin */
        $admin = auth()->user();

        $has_role_prep_vehicle  = $admin->hasRole(AdminRole::role_vehicle_mgr) || $admin->hasRole(config('setting.super_role.name'));
        $has_role_prep_document = $admin->hasRole(AdminRole::role_payment) || $admin->hasRole(config('setting.super_role.name'));

        return $this->response()->withData([
            'role_prep_vehicle'  => $has_role_prep_vehicle,
            'role_prep_document' => $has_role_prep_document,
        ])->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request)
    {
        $admin = auth()->user();

        $role_prep_vehicle  = $admin->hasRole(AdminRole::role_vehicle_mgr) || $admin->hasRole(config('setting.super_role.name'));
        $role_prep_document = $admin->hasRole(AdminRole::role_payment) || $admin->hasRole(config('setting.super_role.name'));

        $input = Validator::make(
            $request->all(),
            [
                'vp_ve_id' => ['bail', 'required', 'integer'],
            ]
           + ($role_prep_vehicle ? [
               'vp_vehicle_check_is' => ['required', Rule::in(YesNo::YES)],
           ] : [])
            + ($role_prep_document ? [
                'vp_annual_check_is'   => ['required', Rule::in(YesNo::YES)],
                'vp_insured_check_is'  => ['required', Rule::in(YesNo::YES)],
                'vp_document_check_is' => ['required', Rule::in(YesNo::YES)],
            ] : []),
            [],
            trans_property(VehiclePreparation::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle, &$customer) {
                if ($validator->failed()) {
                    return;
                }
                // ve_id
                $ve_id   = $request->input('vp_ve_id');
                $vehicle = Vehicle::query()->find($ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('vp_ve_id', 'The vehicle does not exist.');

                    return;
                }

                $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::PENDING], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                if (!$pass) {
                    return;
                }
            })
            ->validate()
        ;

        if ($input['vp_annual_check_is']) {
            $input['vp_annual_check_dt'] = now();
        }
        if ($input['vp_insured_check_is']) {
            $input['vp_insured_check_dt'] = now();
        }
        if ($input['vp_document_check_is']) {
            $input['vp_document_check_dt'] = now();
        }
        if ($input['vp_vehicle_check_is']) {
            $input['vp_vehicle_check_dt'] = now();
        }

        DB::transaction(function () use (&$input, &$vehiclePreparation) {
            /** @var VehiclePreparation $vehiclePreparation */
            $vehiclePreparation = VehiclePreparation::query()
                ->where('ve_id', '=', $input['ve_id'])
                ->where(
                    function (Builder $query) {
                        $query->where('vp_annual_check_is', '=', YesNo::NO)
                            ->orWhere('vp_insured_check_is', '=', YesNo::NO)
                            ->orWhere('vp_vehicle_check_is', '=', YesNo::NO)
                            ->orWhere('vp_document_check_is', '=', YesNo::NO)
                        ;
                    }
                )->first()
            ;
            if (null === $vehiclePreparation) {
                $vehiclePreparation = VehiclePreparation::query()->create($input);
            } else {
                $vehiclePreparation->update($input);
            }

            if (YesNo::YES == $vehiclePreparation->vp_annual_check_is
                && YesNo::YES == $vehiclePreparation->vp_insured_check_is
                && YesNo::YES == $vehiclePreparation->vp_vehicle_check_is
                && YesNo::YES == $vehiclePreparation->vp_document_check_is) {
                $vehiclePreparation->Vehicle->updateStatus(ve_status_rental: VeStatusRental::LISTED);
            }
        });

        return $this->response()->withData($vehiclePreparation)->respond();
    }

    public function show(VehiclePreparation $vehiclePreparation) {}

    public function edit(VehiclePreparation $vehiclePreparation) {}

    public function update(Request $request, VehiclePreparation $vehiclePreparation) {}

    public function destroy(VehiclePreparation $vehiclePreparation)
    {
        DB::transaction(function () use ($vehiclePreparation) {
            $vehiclePreparation->delete();
        });

        return $this->response()->withData($vehiclePreparation)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
