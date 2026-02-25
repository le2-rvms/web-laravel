<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\Iot\IotDeviceBinding;
use App\Services\IotTerminalCommandPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('终端控制')]
class IotTerminalControlController extends Controller
{
    private const array ACTIONS = ['lock', 'unlock', 'horn'];

    #[PermissionAction(PermissionAction::WRITE)]
    public function store(Request $request, IotDeviceBinding $iotDeviceBinding): Response
    {
        $device = null;

        $input = Validator::make($request->all(), [
            'action' => ['required', 'string', 'max:32', Rule::in(self::ACTIONS)],
        ])->after(function (\Illuminate\Validation\Validator $validator) use ($iotDeviceBinding, &$device) {
            if ($validator->failed()) {
                return;
            }

            $device = $iotDeviceBinding->IotDevice;

            if (!$device) {
                $validator->errors()->add('terminal_id', '终端不存在或不属于当前公司。');
            }
        })->validate();

        $publisher = app(IotTerminalCommandPublisher::class);

        $result = $publisher->publish(
            $device->terminal_id,
            $input['action'],
            [
                'timeout' => 5,
            ]
        );

        return $this->response()
            ->withData([
                'terminal_id' => $device->terminal_id,
                'device_id'   => $device->dev_id,
                'topic'       => $result['topic'],
                'action'      => $input['action'],
                'payload'     => $result['payload'],
            ])
            ->withMessages('命令已发布')
            ->respond()
        ;
    }

    public static function labelOptions(Controller $controller): void
    {
        $controller->response()->withExtras(
        );
    }

    protected function options(?bool $with_group_count = false): void
    {
        $this->response()->withExtras(
        );
    }
}
