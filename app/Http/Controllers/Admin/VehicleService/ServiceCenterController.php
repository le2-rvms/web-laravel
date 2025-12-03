<?php

namespace App\Http\Controllers\Admin\VehicleService;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\ScScStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin\Staff;
use App\Models\Vehicle\ServiceCenter;
use App\Models\Vehicle\VehicleAccident;
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

#[PermissionType('修理厂管理')]
class ServiceCenterController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            ScScStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);

        $query = ServiceCenter::indexQuery();

        $paginate = new PaginateService(
            [],
            [['sc.sc_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $builder->where('sc.sc_name', 'like', '%'.$value.'%')
                        ->orWhere('sc.sc_address', 'like', '%'.$value.'%')
                        ->orWhere('sc.contact_name', 'like', '%'.$value.'%')
                        ->orWhere('sc.contact_phone', 'like', '%'.$value.'%')
                        ->orWhere('sc.sc_note', 'like', '%'.$value.'%')
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

        $serviceCenter = new ServiceCenter([
            'sc_status' => ScScStatus::ENABLED,
        ]);

        $this->response()->withExtras(
            Staff::optionsWithRoles(),
        );

        $arr = (string) $serviceCenter;

        return $this->response()->withData($serviceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(ServiceCenter $serviceCenter): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($serviceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(ServiceCenter $serviceCenter): Response
    {
        $this->options();
        $this->response()->withExtras(
            Staff::optionsWithRoles(),
            VehicleRepair::kvList(sc_id: $serviceCenter->sc_id),
            VehicleMaintenance::kvList(sc_id: $serviceCenter->sc_id),
            VehicleAccident::kvList(sc_id: $serviceCenter->sc_id),
        );

        return $this->response()->withData($serviceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?ServiceCenter $serviceCenter): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'sc_name'               => ['bail', 'required', 'string', 'max:255'],
                'sc_address'            => ['bail', 'required', 'string'],
                'contact_name'          => ['bail', 'required'],
                'contact_phone'         => ['bail', 'nullable', 'string', 'max:32'],
                'sc_status'             => ['bail', 'required', Rule::in(ScScStatus::label_keys())],
                'sc_note'               => ['bail', 'nullable', 'string', 'max:255'],
                'permitted_admin_ids'   => ['bail', 'nullable', 'array'],
                'permitted_admin_ids.*' => ['bail', 'integer'],
                //                'contact_mobile'        => ['bail', 'nullable', 'string', 'max:32'],
            ],
            [],
            trans_property(ServiceCenter::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) {
                if (!$validator->failed()) {
                }
            })
        ;
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$serviceCenter) {
            if (null === $serviceCenter) {
                /** @var ServiceCenter $serviceCenter */
                $serviceCenter = ServiceCenter::query()->create($input);
            } else {
                $serviceCenter->update($input);
            }
        });

        $serviceCenter->refresh();

        return $this->response()->withData($serviceCenter)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(ServiceCenter $serviceCenter): Response
    {
        $serviceCenter->delete();

        return $this->response()->withData($serviceCenter)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            ScScStatus::options(),
        );
    }
}
