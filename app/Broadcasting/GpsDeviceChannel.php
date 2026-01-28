<?php

namespace App\Broadcasting;

use App\Models\Admin\Admin;
use App\Models\Iot\IotDeviceBinding;

class GpsDeviceChannel
{
    public function join(Admin $user, string $terminalId): bool
    {
        if (!$user->can('GpsData::read')) {
            return false;
        }

        return IotDeviceBinding::query()
            ->where('db_terminal_id', $terminalId)
            ->exists()
        ;
    }
}
