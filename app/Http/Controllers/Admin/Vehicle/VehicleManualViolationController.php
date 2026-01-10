<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\VehicleManualViolation\VvStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleManualViolation;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('手动违章')]
class VehicleManualViolationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VvStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $query = VehicleManualViolation::indexQuery();

        /** @var Admin $admin */
        $admin = auth()->user();

        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('ve.ve_team_id', $admin->a_team_ids)->orwhereNull('ve.ve_team_id');
            });
        }

        $paginate = new PaginateService(
            [],
            [['vv.vv_violation_datetime', 'desc']],
            ['kw', 'vv_violation_datetime', 'vv_status'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('vv.violation_content', 'ilike', '%'.$value.'%')->orWhere('vv.vv_remark', 'ilike', "%{$value}%");
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(): Response
    {
        $this->options();
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
    public function show(VehicleManualViolation $vehicleManualViolation): Response
    {
        return $this->response()->withData($vehicleManualViolation)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleManualViolation $vehicleManualViolation): Response
    {
        $this->options();

        $vehicleManualViolation->load('Vehicle');

        return $this->response()->withData($vehicleManualViolation)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleManualViolation $vehicleManualViolation): Response
    {
        // 创建验证器实例
        $input = Validator::make(
            $request->all(),
            [
                'vv_ve_id'              => ['required', 'integer', Rule::exists(Vehicle::class, 've_id')],
                'vv_violation_datetime' => ['required', 'date'],
                'vv_violation_content'  => ['nullable', 'string', 'max:200'],
                'vv_location'           => ['nullable', 'string', 'max:255'],
                'vv_fine_amount'        => ['nullable', 'numeric'],
                'vv_penalty_points'     => ['nullable', 'integer'],
                'vv_status'             => ['required', 'integer', Rule::in(VvStatus::label_keys())],
                'vv_remark'             => ['nullable', 'string'],
            ],
            [],
            trans_property(VehicleManualViolation::class)
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle, &$customer) {
            if ($validator->failed()) {
                return;
            }
            if (null === $request->input('vv_vi_id')) {
                // 新增时校验车辆状态，修改时跳过该校验。
                // ve_id
                $ve_id = $request->input('vv_ve_id');

                /** @var Vehicle $vehicle */
                $vehicle = Vehicle::query()->find($ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('vv_ve_id', '车辆不存在');

                    return;
                }

                $pass = $vehicle->check_status(VeStatusService::YES, [], [], $validator);
                if (!$pass) {
                    return;
                }
            }
        })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$vehicleManualViolation) {
            if (null === $vehicleManualViolation) {
                $vehicleManualViolation = VehicleManualViolation::query()->create($input);
            } else {
                $vehicleManualViolation->update($input);
            }
        });

        return $this->response()->withData($vehicleManualViolation)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleManualViolation $vehicleManualViolation): Response
    {
        $vehicleManualViolation->delete();

        return $this->response()->withData($vehicleManualViolation)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VvStatus::options_with_count(VehicleManualViolation::class) : VvStatus::options(),
        );
    }
}
