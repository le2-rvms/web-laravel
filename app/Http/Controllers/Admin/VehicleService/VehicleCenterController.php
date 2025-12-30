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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
                    $builder->where('vc.vc_name', 'like', '%'.$value.'%')->orWhere('vc.vc_address', 'like', '%'.$value.'%')->orWhere('vc.vc_contact_name', 'like', '%'.$value.'%')->orWhere('vc.vc_contact_phone', 'like', '%'.$value.'%')->orWhere('vc.vc_note', 'like', '%'.$value.'%');
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
            // 默认启用修理厂。
            'vc_status' => VcStatus::ENABLED,
        ]);

        $this->response()->withExtras(
            Admin::optionsWithRoles(function (Builder $builder) {
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
            Admin::optionsWithRoles(function (Builder $builder) {
                $builder->role(AdminRole::role_vehicle_service);
            }),
            // 展示该修理厂关联的维修/保养/出险记录。
            VehicleRepair::indexList(function (Builder $query) use ($vehicleCenter) {
                $query->where('vr.vr_vc_id', '=', $vehicleCenter->vc_id);
            }),
            VehicleMaintenance::indexList(function (Builder $query) use ($vehicleCenter) {
                $query->where('vm.vm_vc_id', '=', $vehicleCenter->vc_id);
            }),
            VehicleAccident::indexList(function (Builder $query) use ($vehicleCenter) {
                $query->where('va.va_vc_id', '=', $vehicleCenter->vc_id);
            }),
        );

        return $this->response()->withData($vehicleCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleCenter $vehicleCenter): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'vc_name'          => ['bail', 'required', 'string', 'max:255'],
                'vc_address'       => ['bail', 'required', 'string'],
                'vc_contact_name'  => ['bail', 'required'],
                'vc_contact_phone' => ['bail', 'nullable', 'string', 'max:32'],
                'vc_status'        => ['bail', 'required', Rule::in(VcStatus::label_keys())],
                'vc_note'          => ['bail', 'nullable', 'string', 'max:255'],
                // 允许访问该修理厂的员工列表（ID 数组）。
                'vc_permitted'   => ['bail', 'nullable', 'array'],
                'vc_permitted.*' => ['bail', 'integer'],
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
            ->validate()
        ;

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
