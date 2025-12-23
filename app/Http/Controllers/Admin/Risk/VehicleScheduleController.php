<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VsInspectionType;
use App\Http\Controllers\Controller;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleSchedule;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('待年检')]
class VehicleScheduleController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VsInspectionType::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $query = VehicleSchedule::indexQuery();

        $paginate = new PaginateService(
            [],
            [],
            ['kw', 'vs_inspection_type', 'vs_ve_id'],
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

        $vehicleSchedule = new VehicleSchedule([
            'vs_inspection_date'      => now(),
            'vs_next_inspection_date' => now()->addYear(),
        ]);

        return $this->response()->withData($vehicleSchedule)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleSchedule $vehicleSchedule): Response
    {
        return $this->response()->withData($vehicleSchedule)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleSchedule $vehicleSchedule): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $vehicleSchedule->load('Vehicle');

        return $this->response()->withData($vehicleSchedule)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleSchedule $vehicleSchedule = null): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'vs_inspection_type'      => ['required', 'string', Rule::in(VsInspectionType::label_keys())],
                'vs_ve_id'                => ['required', 'integer'],
                'vs_inspector'            => ['required', 'string', 'max:255'],
                'vs_inspection_date'      => ['required', 'date'],
                'vs_next_inspection_date' => ['required', 'date', 'after:inspection_date'],
                'vs_inspection_amount'    => ['required', 'decimal:0,2', 'gte:0'],
                'vs_remark'               => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('vs_additional_photos'),
            [],
            trans_property(VehicleSchedule::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$vehicle) {
                if ($validator->failed()) {
                    return;
                }
                // ve_id
                $ve_id = $request->input('vs_ve_id');

                /** @var Vehicle $vehicle */
                $vehicle = Vehicle::query()->find($ve_id);
                if (!$vehicle) {
                    $validator->errors()->add('vs_ve_id', 'The vehicle does not exist.');

                    return;
                }

                $pass = $vehicle->check_status(VeStatusService::YES, [], [], $validator);
                if (!$pass) {
                    return;
                }
            })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$vehicle, &$vehicleSchedule) {
            if (null === $vehicleSchedule) {
                $vehicleSchedule = VehicleSchedule::query()->create($input);
            } else {
                $vehicleSchedule->update($input);
            }
        });

        return $this->response()->withData($vehicleSchedule)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleSchedule $vehicleSchedule): Response
    {
        return $this->response()->withData($vehicleSchedule)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_schedule', ['vs_additional_photos'], $this);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function st_vehicle(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
            Vehicle::options(),
        );

        $query   = VehicleSchedule::stQuery();
        $columns = VehicleSchedule::stColumns();

        $paginate = new PaginateService(
            [],
            [],
            [],
            []
        );

        $paginate->paginator($query, $request, [], $columns);

        return $this->response()->withData($paginate)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VsInspectionType::options_with_count(VehicleSchedule::class) : VsInspectionType::options(),
        );
    }
}
