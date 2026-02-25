<?php

namespace App\Http\Controllers\Customer\Device;

use App\Http\Controllers\Controller;
use App\Models\Iot\IotDevice;
use App\Services\IotTerminalCommandPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class IotTerminalControlController extends Controller
{
    private const array ACTIONS = ['lock', 'unlock', 'horn'];

    public function store(Request $request): Response
    {
        $device = null;

        $input = Validator::make($request->all(), [
            'terminal_id' => ['required', 'string', 'max:64'],
            'action'      => ['required', 'string', 'max:32', Rule::in(self::ACTIONS)],
            'command_id'  => ['nullable', 'string', 'max:80'],
        ])->after(function (\Illuminate\Validation\Validator $validator) use (&$device, $request) {
            if ($validator->failed()) {
                return;
            }

            $device = IotDevice::query()
                ->select(['dev_id', 'terminal_id'])
                ->where('terminal_id', $request->input('terminal_id'))
                ->first()
            ;

            if (!$device) {
                $validator->errors()->add('terminal_id', '终端不存在或不属于当前公司。');
            }
        })->validate();

        $publisher = app(IotTerminalCommandPublisher::class);
        $result    = $publisher->publish(
            $device->terminal_id,
            $input['action'],
            [
                'params'     => $input['params'] ?? [],
                'command_id' => $input['command_id'] ?? null,
            ]
        );

        return $this->response()
            ->withData([
                'terminal_id' => $device->terminal_id,
                'device_id'   => $device->dev_id,
                'topic'       => $result['topic'],
                'command_id'  => $result['command_id'],
                'action'      => $input['action'],
                'qos'         => $result['qos'],
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
