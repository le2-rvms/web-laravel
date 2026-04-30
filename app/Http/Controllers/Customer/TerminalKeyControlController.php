<?php

namespace App\Http\Controllers\Customer;

use App\Enum\Iot\EventType_CONN;
use App\Enum\Iot\TerminalCmd;
use App\Enum\SaleContract\ScStatus;
use App\Http\Controllers\Controller;
use App\Models\Iot\IotClientSession;
use App\Models\Iot\IotDevice;
use App\Models\Iot\IotDeviceProduct;
use App\Services\IotTerminalCommandPublisher;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TerminalKeyControlController extends Controller
{
    public function index(): Response
    {
        $bindings    = $this->controllableBindingsQuery()->get();
        $terminalIds = $bindings->pluck('terminal_id')->unique()->values();
        // 客户端只展示可控的 GPS 终端，非目标产品直接过滤掉。
        $terminalIds = $terminalIds->isEmpty()
            ? collect()
            : IotDevice::query()
                ->whereIn('terminal_id', $terminalIds)
                ->where('product_key', '=', IotDeviceProduct::GPS_KEY)
                ->pluck('terminal_id')
        ;
        $bindings = $bindings
            ->whereIn('terminal_id', $terminalIds)
            ->values()
        ;
        // 页面首屏需要同时知道终端在线状态，这里按 terminal_id 聚合后通过 extra 返回。
        $terminalSessionMap = $terminalIds->isEmpty()
            ? collect()
            : IotClientSession::query()
                ->select([
                    'client_id',
                    'last_event_ts',
                    'last_event_type',
                    'last_connect_ts',
                    'last_disconnect_ts',
                    DB::raw("(last_event_type = '".EventType_CONN::CONNECT."') AS is_connected"),
                ])
                ->whereIn('client_id', $terminalIds)
                ->get()
                ->keyBy('client_id')
                ->all()
        ;

        return $this->response()
            ->withExtras(
                TerminalCmd::kv(),
                ['terminalSessionMap' => $terminalSessionMap],
            )
            ->withData($bindings->all())
            ->respond()
        ;
    }

    public function store(Request $request): Response
    {
        $terminalId = null;

        $input = Validator::make($request->all(), [
            'terminal_id' => ['required', 'string', 'max:64'],
            'action'      => ['required', 'string', 'max:32', Rule::in(array_keys(TerminalCmd::kv))],
            'command_id'  => ['nullable', 'string', 'max:80'],
        ])->after(function (\Illuminate\Validation\Validator $validator) use (&$terminalId, $request) {
            if ($validator->failed()) {
                return;
            }

            $terminalId = $request->input('terminal_id');

            // 下发命令前同时校验“当前客户可控”与“终端属于目标产品”。
            if (
                !$this->controllableBindingsQuery()
                    ->where('db.db_terminal_id', '=', $terminalId)
                    ->exists()
                || !IotDevice::query()
                    ->where('terminal_id', '=', $terminalId)
                    ->where('product_key', '=', IotDeviceProduct::GPS_KEY)
                    ->exists()
            ) {
                $validator->errors()->add('terminal_id', '终端不存在或不属于当前客户可控制车辆。');
            }
        })->validate();

        $result = app(IotTerminalCommandPublisher::class)->publish(
            $terminalId,
            TerminalCmd::kv[$input['action']],
        );

        return $this->response()
            ->withData($result)
            ->withMessages('命令已发布')
            ->respond()
        ;
    }

    private function controllableBindingsQuery(): Builder
    {
        $now   = now()->toDateTimeString();
        $cu_id = auth()->id();

        // 只查询当前客户、已签约且绑定仍在有效期内的终端关系。
        return DB::connection()
            ->table('sale_contracts as sc')
            ->join('iot_device_bindings as db', 'db.db_ve_id', '=', 'sc.sc_ve_id')
            ->leftJoin('vehicles as ve', 've.ve_id', '=', 'sc.sc_ve_id')
            ->leftJoin('vehicle_models as vm', 'vm.vm_id', '=', 've.ve_vm_id')
            ->where('sc.sc_cu_id', '=', $cu_id)
            ->where('sc.sc_status', '=', ScStatus::SIGNED)
            ->where('db.db_start_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('db.db_end_at')
                    ->orWhere('db.db_end_at', '>=', $now)
                ;
            })
            ->orderByDesc('sc.sc_id')
            ->orderBy('db.db_id')
            ->select([
                'db.db_terminal_id as terminal_id',
                'sc.sc_id',
                'sc.sc_no',
                'sc.sc_start_date',
                'sc.sc_end_date',
                've.ve_id',
                've.ve_plate_no',
                'vm.vm_brand_name',
                'vm.vm_model_name',
            ])
        ;
    }
}
