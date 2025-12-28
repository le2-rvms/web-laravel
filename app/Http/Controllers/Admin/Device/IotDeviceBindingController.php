<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Iot\IotDevice;
use App\Models\Iot\IotDeviceBinding;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('设备绑定')]
class IotDeviceBindingController extends Controller
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

        $query = IotDeviceBinding::indexQuery();

        $paginate = new PaginateService(
            [],
            // 默认按最新绑定记录排序。
            [['db.db_id', 'desc']],
            [],
            []
        );

        $paginate->paginator($query, $request, []);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(IotDeviceBinding $iotDeviceBinding): Response
    {
        $this->options();
        $this->response()->withExtras(
        );

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        return $this->edit($request, null);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, ?IotDeviceBinding $iotDeviceBinding): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
            Vehicle::options(),
        );

        if (null === $iotDeviceBinding) {
            // 新建时预填开始时间与处理人。
            $iotDeviceBinding = new IotDeviceBinding([
                'start_at'     => now(),
                'processed_by' => Auth::id(),
            ]);
        }

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request): Response
    {
        return $this->update($request, null);
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function update(Request $request, ?IotDeviceBinding $iotDeviceBinding): Response
    {
        $input = Validator::make(
            $request->all(),
            [
                'd_id' => ['required', 'integer', Rule::exists(IotDevice::class)],
                // 仅允许绑定在役车辆。
                've_id'        => ['required', 'integer', Rule::exists(Vehicle::class, 've_id')->where('ve_status_service', VeStatusService::YES)],
                'db_start_at'  => ['required', 'date'],
                'db_end_at'    => ['nullable', 'date', 'after:db_start_at'],
                'db_note'      => ['nullable', 'string', 'max:200'],
                'processed_by' => ['required', Rule::exists(Admin::class, 'id')],
            ],
            trans_property(IotDeviceBinding::class),
        )->after(function (\Illuminate\Validation\Validator $validator) use ($iotDeviceBinding, $request) {
            if ($validator->failed()) {
                return;
            }
            // 如果当前绑定未结束，则同设备不能有其他未结束绑定。
            if (!$request->input('db_end_at')) {
                $count = IotDeviceBinding::query()
                    ->where('d_id', $request->input('d_id'))
                    ->whereNull('db_end_at')
                    ->when($iotDeviceBinding, function (Builder $query) use ($iotDeviceBinding) {
                        // 编辑时排除当前记录，避免误判重复绑定。
                        $query->where($iotDeviceBinding->getKeyName(), '!=', $iotDeviceBinding->db_id);
                    })
                    ->count()
                ;
                if ($count > 0) {
                    $validator->errors()->add('db_end_at', '存在结束时间为空的绑定');

                    return;
                }
            }
        })
            ->validate()
        ;

        DB::transaction(function () use (&$input, &$iotDeviceBinding) {
            if (null === $iotDeviceBinding) {
                $iotDeviceBinding = IotDeviceBinding::query()->create($input);
            } else {
                $iotDeviceBinding->update($input);
            }
        });

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function destroy(IotDeviceBinding $iotDeviceBinding): Response
    {
        DB::transaction(function () use ($iotDeviceBinding) {
            $iotDeviceBinding->delete();
        });

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
