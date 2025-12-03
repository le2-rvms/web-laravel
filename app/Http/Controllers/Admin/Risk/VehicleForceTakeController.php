<?php

namespace App\Http\Controllers\Admin\Risk;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusService;
use App\Enum\Vehicle\VftForceTakeStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleForceTake;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('风控收车管理')]
class VehicleForceTakeController extends Controller
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
        );

        $query = VehicleForceTake::indexQuery();

        $paginate = new PaginateService(
            [],
            [],
            ['kw', 'vft_force_take_status', 'vft_ve_id'],
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
            Customer::options(),
        );

        $vehicleForceTake = new VehicleForceTake([
            'force_take_time' => now()->format('Y-m-d'),
        ]);

        return $this->response()->withData($vehicleForceTake)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleForceTake $vehicleForceTake): Response
    {
        $this->options();

        return $this->response()->withData($vehicleForceTake)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleForceTake $vehicleForceTake): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(),
            Customer::options(),
        );

        $vehicleForceTake->load('Vehicle', 'Customer');

        return $this->response()->withData($vehicleForceTake)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleForceTake $vehicleForceTake = null): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                've_id'             => ['required', 'integer'],
                'cu_id'             => ['required', 'integer'],
                'force_take_time'   => ['required', 'date'],
                'force_take_status' => ['required', 'string', Rule::in(VftForceTakeStatus::label_keys())],
                'reason'            => ['nullable', 'string'],
            ]
            + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(VehicleForceTake::class)
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

        DB::transaction(function () use (&$input, &$vehicle, &$vehicleForceTake) {
            if (null === $vehicleForceTake) {
                $vehicleForceTake = VehicleForceTake::query()->create($input);
            } else {
                $vehicleForceTake->update($input);
            }
        });

        return $this->response()->withData($vehicleForceTake)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleForceTake $vehicleForceTake): Response
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

        $vehicleForceTake->delete();

        return $this->response()->withData($vehicleForceTake)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_force_take', ['additional_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            $with_group_count ? VftForceTakeStatus::options_with_count(VehicleForceTake::class) : VftForceTakeStatus::options(),
        );
    }
}
