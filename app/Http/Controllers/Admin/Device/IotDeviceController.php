<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Iot\IotDevice;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('设备')]
class IotDeviceController extends Controller
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

        $query = IotDevice::indexQuery();

        $paginate = new PaginateService(
            [],
            // 默认按最新绑定记录排序。
            [['dev.dev_id', 'desc']],
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $like = '%'.$value.'%';
                    $builder->where('dev.dev_id', 'ilike', $like);
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(IotDevice $iotDevice): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        $iotDevice->load('IotDeviceProduct');

        return $this->response()->withData($iotDevice)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        return $this->edit($request, null);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, ?IotDevice $iotDevice): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
        );

        if (null === $iotDevice) {
            // 新建时预填开始时间与处理人。
            $iotDevice = new IotDevice([
                'db_processed_by' => Auth::id(),
            ]);
        } else {
            $iotDevice->load('Vehicle', 'GpsDevice');
        }

        return $this->response()->withData($iotDevice)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?IotDevice $iotDevice): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'db_terminal_id'  => ['required', 'string'],
                'db_ve_id'        => ['required', 'integer', Rule::exists(Vehicle::class, 've_id')->where('ve_status_service', VeStatusService::YES)], // 仅允许绑定在役车辆。
                'db_start_at'     => ['required', 'date'],
                'db_end_at'       => ['nullable', 'date', 'after:db_start_at'],
                'db_note'         => ['nullable', 'string', 'max:200'],
                'db_processed_by' => ['required', Rule::exists(Admin::class, 'id')],
            ],
            trans_property(IotDevice::class),
        )->after(function (\Illuminate\Validation\Validator $validator) {
            if ($validator->failed()) {
                return;
            }
        })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$iotDevice) {
            if (null === $iotDevice) {
                $iotDevice = IotDevice::query()->create($input);
            } else {
                $iotDevice->update($input);
            }
        });

        return $this->response()->withData($iotDevice)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(IotDevice $iotDevice): Response
    {
        DB::transaction(function () use ($iotDevice) {
            $iotDevice->delete();
        });

        return $this->response()->withData($iotDevice)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
