<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Enum\Iot\TerminalCmd;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Admin\Admin;
use App\Models\Iot\IotClientAuthEvent;
use App\Models\Iot\IotClientCmdEvent;
use App\Models\Iot\IotClientConnEvent;
use App\Models\Iot\IotClientSession;
use App\Models\Iot\IotDevice;
use App\Models\Iot\IotDeviceBinding;
use App\Models\Iot\IotGpsPositionHistory;
use App\Models\Vehicle\Vehicle;
use App\Services\PaginateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            ['kw'],
            []
        );

        $paginate->paginator($query, $request, [
            'kw__func' => function ($value, Builder $builder) {
                $builder->where(function (Builder $builder) use ($value) {
                    $like = '%'.$value.'%';
                    $builder->where('db.db_terminal_id', 'ilike', $like)
                        ->orWhere('ve.ve_plate_no', 'ilike', $like)
                    ;
                });
            },
        ]);

        return $this->response()->withData($paginate)->respond();
    }

    #[PermissionAction(PermissionAction::READ)]
    public function show(IotDeviceBinding $iotDeviceBinding): Response
    {
        $this->options();
        $terminalId = $iotDeviceBinding->db_terminal_id;

        $exists = IotDevice::query()
            ->where('terminal_id', $terminalId)
            ->exists()
        ;

        if (!$exists) {
            throw ValidationException::withMessages([
                'db_terminal_id' => '设备不属于当前公司或不存在。',
            ]);
        }

        $iotDeviceBinding->load('Vehicle', 'IotDevice');

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function create(Request $request): Response
    {
        $this->response()->withExtras(
            IotDevice::options(),
            Vehicle::options(),
        );

        $iotDeviceBinding = new IotDeviceBinding([
            'db_start_at' => now()->format('Y-m-d H:i:00'),
        ]);

        return $this->response()->withData($iotDeviceBinding)->respond();
    }

    #[PermissionAction(PermissionAction::WRITE)]
    public function edit(Request $request, ?IotDeviceBinding $iotDeviceBinding): Response
    {
        $this->options();

        $this->response()->withExtras(
            Admin::optionsWithRoles(),
            Vehicle::options(),
            TerminalCmd::kv(),
        );

        $this->response()->withExtras(
            IotGpsPositionHistory::indexList(function (Builder $query) use ($iotDeviceBinding) {
                $query->where('terminal_id', '=', $iotDeviceBinding->db_terminal_id)
                    ->whereBetween('gps_time', [$iotDeviceBinding->db_start_at, $iotDeviceBinding->db_end_at ?? now()->format('Y-m-d H:i:s')])
                    ->orderByDesc('gps_time')
                    ->limit(200)
                ;
            }),
            IotDeviceBinding::indexList(function (Builder $query) use ($iotDeviceBinding) {
                $query->where('db.db_terminal_id', '=', $iotDeviceBinding->db_terminal_id)
                    ->orderByDesc('db.db_id')
                    ->limit(200)
                ;
            }),
            IotClientSession::indexList(function (Builder $query) use ($iotDeviceBinding) {
                $query->where('client_id', '=', $iotDeviceBinding->db_terminal_id)
                    ->whereBetween('last_connect_ts', [$iotDeviceBinding->db_start_at, $iotDeviceBinding->db_end_at ?? now()->format('Y-m-d H:i:s')])
                    ->orderByDesc('last_connect_ts')
                    ->limit(200)
                ;
            }),
            IotClientConnEvent::indexList(function (Builder $query) use ($iotDeviceBinding) {
                $query->where('client_id', '=', $iotDeviceBinding->db_terminal_id)
                    ->whereBetween('ts', [$iotDeviceBinding->db_start_at, $iotDeviceBinding->db_end_at ?? now()->format('Y-m-d H:i:s')])
                    ->orderByDesc('id')
                    ->limit(200)
                ;
            }),
            IotClientCmdEvent::indexList(function (Builder $query) use ($iotDeviceBinding) {
                $query->where('client_id', '=', $iotDeviceBinding->db_terminal_id)
                    ->whereBetween('ts', [$iotDeviceBinding->db_start_at, $iotDeviceBinding->db_end_at ?? now()->format('Y-m-d H:i:s')])
                    ->orderByDesc('id')
                    ->limit(200)
                ;
            }),
            IotClientAuthEvent::indexList(function (Builder $query) use ($iotDeviceBinding) {
                $query->where('client_id', '=', $iotDeviceBinding->db_terminal_id)
                    ->whereBetween('ts', [$iotDeviceBinding->db_start_at, $iotDeviceBinding->db_end_at ?? now()->format('Y-m-d H:i:s')])
                    ->orderByDesc('id')
                    ->limit(200)
                ;
            }),
        );
        $iotDeviceBinding->load('Vehicle', 'IotDevice');

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
                'db_terminal_id' => ['required', 'string'],
                'db_ve_id'       => ['required', 'integer', Rule::exists(Vehicle::class, 've_id')], // 仅允许绑定在役车辆。 ->where('ve_status_service', VeStatusService::YES)
                'db_start_at'    => ['required', 'date'],
                'db_end_at'      => ['nullable', 'date', 'after:db_start_at'],
                'db_note'        => ['nullable', 'string', 'max:200'],
            ],
            trans_property(IotDeviceBinding::class),
        )->after(function (\Illuminate\Validation\Validator $validator) use ($iotDeviceBinding, $request) {
            if ($validator->failed()) {
                return;
            }
            // 如果当前绑定未结束，则同设备不能有其他未结束绑定。
            if (!$request->input('db_end_at')) {
                $count = IotDeviceBinding::query()
                    ->where('db_terminal_id', $request->input('db_terminal_id'))
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
