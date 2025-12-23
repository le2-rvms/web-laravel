<?php

namespace App\Http\Controllers\Admin\Vehicle;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\VehicleModel\VmStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle\VehicleModel;
use App\Services\PaginateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('车型')]
class VehicleModelController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VmStatus::labelOptions()
        );
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create()
    {
        $this->options();
        $this->response()->withExtras();

        $vehicleModel = new VehicleModel([
            'vm_status' => VmStatus::ENABLED,
        ]);

        return $this->response()->withData($vehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query  = VehicleModel::indexQuery();
        $column = VehicleModel::indexColumns();

        $paginate = new PaginateService(
            [],
            [['vm.vm_id', 'asc']],
            [],
            []
        );

        $paginate->paginator($query, $request, [], $column);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(VehicleModel $vehicleModel): Response
    {
        return $this->response()->withData($vehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleModel $vehicleModel): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($vehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?VehicleModel $vehicleModel): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'vm_id'         => ['nullable', Rule::exists(VehicleModel::class, 'vm_id')],
                'vm_brand_name' => ['required', 'string', 'max:50'],
                'vm_model_name' => ['required', 'string', 'max:50',
                    Rule::unique(VehicleModel::class)->where('vm_brand_name', $request->input('vm_brand_name'))->ignore($vehicleModel),
                ],
                'vm_status' => ['required', Rule::in(VmStatus::label_keys())],
            ],
            [],
            trans_property(VehicleModel::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use (&$vehicle) {
                if ($validator->failed()) {
                    return;
                }
            })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$vehicleModel) {
            if (null === $vehicleModel) {
                $vehicleModel = VehicleModel::query()->create($input);
            } else {
                $vehicleModel->update($input);
            }
        });

        return $this->response()->withData($vehicleModel)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(VehicleModel $vehicleModel): Response
    {
        $vehicleModel->delete();

        return $this->response()->withData($vehicleModel)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VmStatus::options()
        );
    }
}
