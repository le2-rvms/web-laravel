<?php

namespace App\Http\Controllers\Admin\Device;

use App\Attributes\PermissionAction;
use App\Attributes\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\Iot\IotDevice;
use App\Services\BleKeyDeriver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

#[PermissionType('BLE设备密钥')]
class DeviceBleKeyController extends Controller
{
    #[PermissionAction(PermissionAction::READ)]
    public function create(Request $request, IotDevice $iot_device): Response
    {
        $this->options();

        try {
            $terminalNo = (string) $request->query('terminal_no', (string) $iot_device->terminal_id);
            $result     = BleKeyDeriver::deriveByTerminalNo($terminalNo);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'terminal_no' => $exception->getMessage(),
            ]);
        }

        return $this->response()
            ->withData($result)
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
