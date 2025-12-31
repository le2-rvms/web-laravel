<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Admin\ATeamLimit;
use App\Enum\Sale\VtChangeStatus;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Sale\SaleContract;
use App\Models\Sale\VehicleTemp;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use App\Services\Uploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('临时车')]
class VehicleTempController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VtChangeStatus::labelOptions(),
        );
    }

    #[PermissionAction(PermissionAction::READ)]
    public function index(Request $request): Response
    {
        $this->options(true);
        $this->response()->withExtras(
        );

        $query   = VehicleTemp::indexQuery();
        $columns = VehicleTemp::indexColumns();

        // 车队查询条件
        if (($admin->a_team_limit->value ?? null) === ATeamLimit::LIMITED && $admin->a_team_ids) {
            $query->where(function (Builder $query) use ($admin) {
                $query->whereIn('cu.cu_team_id', $admin->a_team_ids)->orWhereNull('cu.cu_team_id');
            });
        }

        $paginate = new PaginateService(
            [],
            [['vt.vt_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, [], $columns);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        /** @var SaleContract $saleContract */
        $saleContract = null;
        $input        = Validator::make(
            $request->all(),
            [
                'sc_id' => ['nullable', 'integer'],
            ],
            [],
            trans_property(SaleContract::class)
        )
            ->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$saleContract, &$vehicle0) {
                if ($validator->failed()) {
                    return;
                }
                if ($sc_id = $request->query('sc_id')) {
                    $saleContract = SaleContract::query()->findOrFail($sc_id);

                    //                    $saleContract->load('Vehicle');

                    $vehicle0 = $saleContract->Vehicle;

                    // 仅租赁中的车辆允许申请临时换车。
                    $pass = $vehicle0->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [], $validator);
                    if (!$pass) {
                        return;
                    }
                }
            })
            ->validate()
        ;

        $this->options();
        $this->response()->withExtras(
            SaleContract::options(
                function (Builder $builder) {
                    $builder->whereIn('sc.sc_status', [ScStatus::SIGNED]);
                }
            ),
            Vehicle::options(
                function (Builder $builder) {
                    $builder->whereIn('ve_status_rental', [VeStatusRental::LISTED])
                        ->whereIn('ve_status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
        );

        $vehicleTemp = new VehicleTemp([
            'vt_sc_id'             => $saleContract?->sc_id,
            'vt_change_start_date' => now(),
            'vt_change_status'     => VtChangeStatus::IN_PROGRESS,
        ]);
        $vehicleTemp->SaleContract = $saleContract;

        return $this->response()->withData($vehicleTemp)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        /** @var Vehicle $vehicle0 */
        /** @var Vehicle $vehicle */
        /** @var SaleContract $saleContract */
        $vehicle0 = $vehicle = $saleContract = null;

        $input = Validator::make(
            $request->all(),
            [
                'vt_sc_id'             => ['bail', 'required', 'integer'],
                'vt_change_start_date' => ['bail', 'nullable', 'required', 'date'],
                'vt_change_end_date'   => ['bail', 'nullable', 'required', 'date', 'afterOrEqual:vt_change_start_date'],
                'vt_change_status'     => ['bail', 'nullable', 'required', Rule::in(VtChangeStatus::label_keys())],
                'vt_new_ve_id'         => ['bail', 'required'],
                'vt_remark'            => ['bail', 'nullable', 'string'],
            ]
                + Uploader::validator_rule_upload_array('vt_additional_photos'),
            [],
            trans_property(VehicleTemp::class)
        )->after(function (\Illuminate\Validation\Validator $validator) use ($request, &$saleContract, &$vehicle0, &$vehicle) {
            if ($validator->failed()) {
                return;
            }
            $saleContract = SaleContract::query()->findOrFail($request->input('vt_sc_id'));

            $vehicle0 = $saleContract->Vehicle;

            $pass = $vehicle0->check_status(VeStatusService::YES, [VeStatusRental::RENTED], [], $validator);
            if (!$pass) {
                return;
            }

            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->find($request->input('vt_new_ve_id'));
            if (!$vehicle) {
                $validator->errors()->add('vt_ve_id', '车辆不存在');

                return;
            }

            $pass = $vehicle->check_status(VeStatusService::YES, [VeStatusRental::LISTED], [VeStatusDispatch::NOT_DISPATCHED], $validator);
            if (!$pass) {
                return;
            }

            if ($vehicle->ve_id === $saleContract->sc_ve_id) {
                $validator->errors()->add('vt_new_ve_id', '请选择另外一辆车。');

                return;
            }
        })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$vehicleTemp, &$saleContract, $vehicle) {
            $vehicleTemp = VehicleTemp::query()
                ->create($input + ['vt_current_ve_id' => $saleContract->sc_ve_id])
            ;

            // 根据变更状态同步合同临时车字段。
            switch ($input['vt_change_status']) {
                case VtChangeStatus::IN_PROGRESS:
                    $saleContract->sc_ve_id_temp = $vehicle->ve_id;
                    $saleContract->save();

                    break;

                case VtChangeStatus::COMPLETED:
                    $saleContract->sc_ve_id_temp = null;
                    $saleContract->save();

                    break;
            }

            // 新车进入租赁状态，避免被再次分配。
            $vehicle->updateStatus(ve_status_rental: VeStatusRental::RENTED);
        });

        return $this->response()->withData($vehicleTemp)->respond();
    }

    public function show(VehicleTemp $vehicleTemp) {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(VehicleTemp $vehicleTemp): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        $vehicleTemp->load('CurrentVehicle', 'NewVehicle');

        return $this->response()->withData($vehicleTemp)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, VehicleTemp $vehicleTemp): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'vt_change_start_date' => ['bail', 'nullable', 'required', 'date'],
                'vt_change_end_date'   => ['bail', 'nullable', 'required', 'date', 'afterOrEqual:change_start_date'],
                'vt_change_status'     => ['bail', 'nullable', 'required', Rule::in(VtChangeStatus::label_keys())],
                'vt_remark'            => ['bail', 'nullable', 'string'],
            ]
                + Uploader::validator_rule_upload_array('vt_additional_photos'),
            [],
            trans_property(VehicleTemp::class)
        )->after(function (\Illuminate\Validation\Validator $validator) {
            if ($validator->failed()) {
                return;
            }
        })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$vehicleTemp) {
            $vehicleTemp->update($input);

            $change_status_changed = $vehicleTemp->wasChanged('vt_change_status');
            if ($change_status_changed) {
                $saleContract = $vehicleTemp->SaleContract;

                // 状态变更时同步合同临时车字段。
                switch ($input['vt_change_status']) {
                    case VtChangeStatus::IN_PROGRESS:
                        $saleContract->sc_ve_id_temp = $vehicleTemp->vt_new_ve_id;
                        $saleContract->save();

                        break;

                    case VtChangeStatus::COMPLETED:
                        $saleContract->sc_ve_id_temp = null;
                        $saleContract->save();

                        break;
                }
            }
        });

        return $this->response()->withData($vehicleTemp)->respond();
    }

    public function destroy(VehicleTemp $vehicleTemp) {}

    #[PermissionAction(PermissionAction::WRITE)]
    public function upload(Request $request): Response
    {
        return Uploader::upload($request, 'vehicle_change', ['vt_additional_photos'], $this);
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
            VtChangeStatus::options(),
        );
    }
}
