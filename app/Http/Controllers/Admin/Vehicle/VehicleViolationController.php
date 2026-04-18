<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\VehicleViolation\VvPaymentStatus;
use App\Enum\VehicleViolation\VvProcessStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleViolation;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('违章')]
class VehicleViolationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VvPaymentStatus::labelOptions(),
            VvProcessStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::optionsNo(),
        );

        $query  = VehicleViolation::indexQuery();
        $column = VehicleViolation::indexColumns();

        /** @var Admin $admin */
        $admin = auth()->user();

        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            // 车队受限时仅看本车队或未分配车队的车辆违章。
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('ve.ve_team_id', $admin->a_team_ids)->orwhereNull('ve.ve_team_id');
            });
        }

        $paginate = new PaginateService(
            [],
            [['vv.vv_violation_datetime', 'desc']],
            ['kw', 'vv_plate_no', 'vv_violation_datetime', 'vv_process_status', 'vv_payment_status'],
            []
        );

        $paginate->paginator(
            $query,
            $request,
            [
                'kw__func' => function ($value, Builder $builder) {
                    $builder->where(function (Builder $builder) use ($value) {
                        $builder->where('violation_content', 'ilike', '%'.$value.'%')->orWhere('vv_remark', 'ilike', "%{$value}%");
                    });
                },
            ],
            $column,
        );

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(): Response
    {
        $this->response()->withExtras(
            Vehicle::options(),
        );

        return $this->response()->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleViolation $vehicleViolation): Response
    {
        return $this->response()->withData($vehicleViolation)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleViolation $vehicleViolation): Response
    {
        $vehicleViolation->load('Vehicle');

        return $this->response()->withData($vehicleViolation)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleViolation $vehicleViolation): Response
    {
        // 违章记录只允许补充备注，其他字段由外部来源维护。
        $input = Validator::make(
            $request->all(),
            [
                'vv_remark' => ['required', 'string'],
            ],
            [],
            trans_property(VehicleViolation::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle) {})
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$vehicleViolation) {
            if (null === $vehicleViolation) {
                $vehicleViolation = $vehicleViolation->create($input);
            } else {
                $vehicleViolation->update($input);
            }
        });

        return $this->response()->withData($vehicleViolation)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleViolation $vehicleViolation): Response
    {
        $vehicleViolation->delete();

        return $this->response()->withData($vehicleViolation)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VvPaymentStatus::options_with_count(VehicleViolation::class) : VvPaymentStatus::options(),
            $with_group_count ? VvProcessStatus::options_with_count(VehicleViolation::class) : VvProcessStatus::options(),
        );
    }
}
