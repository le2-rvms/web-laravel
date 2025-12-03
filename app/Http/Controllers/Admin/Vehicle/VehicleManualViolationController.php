<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VmvStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleManualViolation;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('手动违章管理')]
class VehicleManualViolationController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VmvStatus::labelOptions(),
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

        $paginate = new PaginateService(
            [],
            [['vmv.violation_datetime', 'desc']],
            ['kw', 'vmv_violation_datetime', 'vmv_status'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('vmv.violation_content', 'like', '%'.$value.'%')
                        ->orWhere('vmv.vmv_remark', 'like', "%{$value}%")
                    ;
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
        $validator = Validator::make(
            $request->all(),
            [
                've_id'              => ['required', 'integer', Rule::exists(Vehicle::class, 've_id')],
                'violation_datetime' => ['required', 'date'],
                'violation_content'  => ['nullable', 'string', 'max:200'],
                'location'           => ['nullable', 'string', 'max:255'],
                'fine_amount'        => ['nullable', 'numeric'],
                'penalty_points'     => ['nullable', 'integer'],
                'status'             => ['required', 'integer', Rule::in(VmvStatus::label_keys())],
                'vmv_remark'         => ['nullable', 'string'],
            ],
            [],
            trans_property(VehicleManualViolation::class)
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle, &$customer) {
            if (!$validator->failed()) {
                if (null === $request->input('vi_id')) {
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
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

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
            $with_group_count ? VmvStatus::options_with_count(VehicleManualViolation::class) : VmvStatus::options(),
        );
    }
}
