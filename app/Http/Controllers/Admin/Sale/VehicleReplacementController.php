<?php

namespace App\Http\Controllers\Admin\Sale;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Sale\SoOrderStatus;
use App\Enum\Sale\VrReplacementStatus;
use App\Enum\Sale\VrReplacementType;
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

#[PermissionType('换车管理')]
class VehicleReplacementController extends Controller
{
    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
            VrReplacementType::labelOptions(),
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
            Vehicle::options(
                where: function (Builder $builder) {
                    $builder->whereIn('status_rental', [VeStatusRental::LISTED])
                        ->whereIn('status_dispatch', [VeStatusDispatch::NOT_DISPATCHED])
                    ;
                }
            ),
            SaleOrder::options(
                where: function (Builder $builder) {
                    $builder->whereIn('so.order_status', [SoOrderStatus::SIGNED]);
                }
            ),
        );

        $vehicleReplacement = new VehicleReplacement([
            //            'so_id'                  => $saleOrder?->so_id,
            'replacement_type'       => VrReplacementType::TEMPORARY,
            'replacement_start_date' => now(),
            'replacement_status'     => VrReplacementStatus::IN_PROGRESS,
        ]);

        return $this->response()->withData($vehicleReplacement)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        /** @var Vehicle $vehicle0 */
        $vehicle0 = null;

        /** @var Vehicle $vehicle */
        $vehicle = null;

        $input0 = Validator::make(
            $request->all(),
            [
                'replacement_type' => ['bail', 'required', Rule::in(VrReplacementType::label_keys())],
            ]
            + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(VehicleReplacement::class)
        )->validated();

        $validator = match ($input0['replacement_type']) {
            VrReplacementType::TEMPORARY => Validator::make(
                $request->all(),
                [
                    'replacement_type'       => ['bail', 'required', Rule::in(VrReplacementType::label_keys())],
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
            ),

            VrReplacementType::PERMANENT => Validator::make(
                $request->all(),
                [
                    'replacement_date' => ['bail', 'nullable', 'required', 'date'],
                ]
                + Uploader::validator_rule_upload_array('additional_photos'),
                [],
                trans_property(VehicleReplacement::class)
            ),
        };

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

        $input = $input0 + $validator->validated();

        DB::transaction(function () use (&$input, &$vehicleReplacement, &$saleOrder, $vehicle) {
            $vehicleReplacement = VehicleReplacement::query()
                ->create($input + ['current_ve_id' => $saleOrder->ve_id])
            ;

            //            $vehicle0->updateStatus(status_rental: VeStatusRental::LISTED);

            //            $saleOrder->ve_id = $vehicle->ve_id;
            //            $saleOrder->save();

            $vehicle->updateStatus(status_rental: VeStatusRental::RENTED); // todo 不应该是已租赁，毕竟没有产生效益。
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
        $input0 = Validator::make(
            $request->all(),
            [
                'replacement_type' => ['bail', 'required', Rule::in(VrReplacementType::label_keys())],
            ]
            + Uploader::validator_rule_upload_array('additional_photos'),
            [],
            trans_property(VehicleReplacement::class)
        )->validated();

        $validator = match ($input0['replacement_type']) {
            VrReplacementType::TEMPORARY => Validator::make(
                $request->all(),
                [
                    //                'so_id'            => ['bail', 'required', 'integer'],
                    'replacement_start_date' => ['bail', 'nullable', 'required', 'date'],
                    'replacement_end_date'   => ['bail', 'nullable', 'required', 'date', 'afterOrEqual:replacement_start_date'],
                    'replacement_status'     => ['bail', 'nullable', 'required', Rule::in(VrReplacementStatus::label_keys())],
                    //                'new_ve_id'        => ['bail', 'required'],
                    'vr_remark' => ['bail', 'nullable', 'string'],
                ]
                + Uploader::validator_rule_upload_array('additional_photos'),
                [],
                trans_property(VehicleReplacement::class)
            ),

            VrReplacementType::PERMANENT => Validator::make(
                $request->all(),
                [
                    'replacement_date' => ['bail', 'nullable', 'required', 'date'],
                ]
                + Uploader::validator_rule_upload_array('additional_photos'),
                [],
                trans_property(VehicleReplacement::class)
            ),
        };

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
            VrReplacementType::options(),
            VrReplacementStatus::options(),
        );
    }
}
