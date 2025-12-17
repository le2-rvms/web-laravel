<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VcStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminRole;
use App\Models\Vehicle\VehicleAccident;
use App\Models\Vehicle\VehicleCenter;
use App\Models\Vehicle\VehicleMaintenance;
use App\Models\Vehicle\VehicleRepair;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('修理厂')]
class VehicleCenterController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VcStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = VehicleCenter::indexQuery();

        $paginate = new PaginateService(
            [],
            [['vc.vc_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('vc.vc_name', 'like', '%'.$value.'%')
                        ->orWhere('vc.vc_address', 'like', '%'.$value.'%')
                        ->orWhere('vc.vc_contact_name', 'like', '%'.$value.'%')
                        ->orWhere('vc.vc_contact_phone', 'like', '%'.$value.'%')
                        ->orWhere('vc.vc_note', 'like', '%'.$value.'%')
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->options();

        $vehicleCenter = new VehicleCenter([
            'vc_status' => VcStatus::ENABLED,
        ]);

        $this->response()->withExtras(
            Admin::optionsWithRoles(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_vehicle_service);
            }),
        );

        return $this->response()->withData($vehicleCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleCenter $vehicleCenter): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($vehicleCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleCenter $vehicleCenter): Response
    {
        $this->options();
        $this->response()->withExtras(
            Admin::optionsWithRoles(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->role(AdminRole::role_vehicle_service);
            }),
            VehicleRepair::kvList(vc_id: $vehicleCenter->vc_id),
            VehicleMaintenance::kvList(vc_id: $vehicleCenter->vc_id),
            VehicleAccident::kvList(vc_id: $vehicleCenter->vc_id),
        );

        return $this->response()->withData($vehicleCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleCenter $vehicleCenter): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'vc_name'          => ['bail', 'required', 'string', 'max:255'],
                'vc_address'       => ['bail', 'required', 'string'],
                'vc_contact_name'  => ['bail', 'required'],
                'vc_contact_phone' => ['bail', 'nullable', 'string', 'max:32'],
                'vc_status'        => ['bail', 'required', Rule::in(VcStatus::label_keys())],
                'vc_note'          => ['bail', 'nullable', 'string', 'max:255'],
                'vc_permitted'     => ['bail', 'nullable', 'array'],
                'vc_permitted.*'   => ['bail', 'integer'],
                //                'contact_mobile'        => ['bail', 'nullable', 'string', 'max:32'],
            ],
            [],
            trans_property(VehicleCenter::class)
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

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$vehicleCenter) {
            if (null === $vehicleCenter) {
                /** @var VehicleCenter $vehicleCenter */
                $vehicleCenter = VehicleCenter::query()->create($input);
            } else {
                $vehicleCenter->update($input);
            }
        });

        $vehicleCenter->refresh();

        return $this->response()->withData($vehicleCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleCenter $vehicleCenter): Response
    {
        $vehicleCenter->delete();

        return $this->response()->withData($vehicleCenter)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VcStatus::options(),
        );
    }
}
