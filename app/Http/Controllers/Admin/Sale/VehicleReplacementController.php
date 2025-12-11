<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\AdmTeamLimit;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\VrReplacementStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\VehicleReplacement;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('临时车')]
class VehicleReplacementController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VrReplacementStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query   = VehicleReplacement::indexQuery();
        $columns = VehicleReplacement::indexColumns();

        // 车队查询条件
        if (($admin->team_limit->value ?? null) === AdmTeamLimit::LIMITED && $admin->team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('cu.cu_team_id', $admin->team_ids)->orWhereNull('cu.cu_team_id');
            });
        }

        $paginate = new PaginateService(
            [],
            [['vr.vr_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, [], $columns);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        /** @var SaleOrder $saleOrder */
        $saleOrder = null;
        $validator = Validator::make(
            $request->all(),
            [
                'so_id' => ['nullable', 'integer'],
            ],
            [],
            []
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$saleOrder, &$vehicle0) {
                if (!$validator->failed()) {
                    if ($so_id = $request->get('so_id')) {
                        $saleOrder = SaleOrder::query()->findOrFail($so_id);

                        $saleOrder->load('Vehicle');

                        $vehicle0 = $saleOrder->Vehicle;

                        $pass = $vehicle0->check_status(VeStatusService::YES, [VeStatusRental::RESERVED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                        if (!$pass) {
                            return;
                        }
                    }
                }
            })
        ;

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $this->options();
        $this->response()->withExtras(
            SaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::SIGNED]);
                }
            ),
            Vehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::LISTED])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
        );

        $vehicleReplacement = new VehicleReplacement([
            //            'so_id'                  => $saleOrder?->so_id,
            'replacement_start_date' => now(),
            'replacement_status'     => VrReplacementStatus::IN_PROGRESS,
        ]);

        return $this->response()->withData($vehicleReplacement)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        /** @var Vehicle $vehicle0 */
        /** @var Vehicle $vehicle */
        /** @var SaleOrder $saleOrder */
        $vehicle0 = $vehicle = $saleOrder = null;

        $validator = Validator::make(
            $request->all(),
            [
                'so_id'                  => ['bail', 'required', 'integer'],
                'replacement_start_date' => ['bail', 'nullable', 'required', 'date'],
                'replacement_end_date'   => ['bail', 'nullable', 'required', 'date', 'afterOrEqual:replacement_start_date'],
                'replacement_status'     => ['bail', 'nullable', 'required', Rule::in(VrReplacementStatus::label_keys())],
                'new_ve_id'              => ['bail', 'required'],
                'vr_remark'              => ['bail', 'nullable', 'string'],
            ]
                + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(VehicleReplacement::class)
        );

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$saleOrder, &$vehicle0, &$vehicle) {
            if (!$validator->failed()) {
                $saleOrder = SaleOrder::query()->findOrFail($request->input('so_id'));

                $vehicle0 = $saleOrder->Vehicle;

                $pass = $vehicle0->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [VeStatusDispatch::DISPATCHED], $validator);
                if (!$pass) {
                    return;
                }

                /** @var Vehicle $vehicle */
                $vehicle = Vehicle::query()->find($request->input('new_ve_id'));
                if (!$vehicle) {
                    $validator->errors()->add('ve_id', 'The vehicle does not exist.');

                    return;
                }

                $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::LISTED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
                if (!$pass) {
                    return;
                }

                if ($vehicle->ve_id === $saleOrder->ve_id) {
                    $validator->errors()->add('new_ve_id', '请选择另外一辆车。');

                    return;
                }
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$vehicleReplacement, &$saleOrder, $vehicle) {
            $vehicleReplacement = VehicleReplacement::query()
                ->create($input + ['current_ve_id' => $saleOrder->ve_id])
            ;

            switch ($input['replacement_status']) {
                case VrReplacementStatus::IN_PROGRESS:
                    $saleOrder->so_ve_id_replace = $vehicle->ve_id;
                    $saleOrder->save();

                    break;

                case VrReplacementStatus::COMPLETED:
                    $saleOrder->so_ve_id_replace = null;
                    $saleOrder->save();

                    break;
            }

            $vehicle->updateStatus(status_rental: VeStatusRental::RENTED);
        });

        return $this->response()->withData($vehicleReplacement)->respond();
    }

    public function show(VehicleReplacement $vehicleReplacement) {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleReplacement $vehicleReplacement): Response
    {
        $this->options();
        $this->response()->withExtras(
            Vehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::PENDING])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
        );

        $vehicleReplacement->load('CurrentVehicle', 'NewVehicle');

        return $this->response()->withData($vehicleReplacement)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, VehicleReplacement $vehicleReplacement): Response
    {
        $validator = Validator::make(
            $request->all(),
            [
                'replacement_start_date' => ['bail', 'nullable', 'required', 'date'],
                'replacement_end_date'   => ['bail', 'nullable', 'required', 'date', 'afterOrEqual:replacement_start_date'],
                'replacement_status'     => ['bail', 'nullable', 'required', Rule::in(VrReplacementStatus::label_keys())],
                'vr_remark'              => ['bail', 'nullable', 'string'],
            ]
                + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(VehicleReplacement::class)
        );

        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if (!$validator->failed()) {
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $input = $validator->validated();

        DB::transaction(function () use (&$input, &$vehicleReplacement) {
            $vehicleReplacement->update($input);

            $replacement_status_changed = $vehicleReplacement->wasChanged('replacement_status');
            if ($replacement_status_changed) {
                $saleOrder = $vehicleReplacement->SaleOrder;

                switch ($input['replacement_status']) {
                    case VrReplacementStatus::IN_PROGRESS:
                        $saleOrder->so_ve_id_replace = $vehicleReplacement->new_ve_id;
                        $saleOrder->save();

                        break;

                    case VrReplacementStatus::COMPLETED:
                        $saleOrder->so_ve_id_replace = null;
                        $saleOrder->save();

                        break;
                }
            }
        });

        return $this->response()->withData($vehicleReplacement)->respond();
    }

    public function destroy(VehicleReplacement $vehicleReplacement) {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_replacement', ['additional_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VrReplacementStatus::options(),
        );
    }
}
