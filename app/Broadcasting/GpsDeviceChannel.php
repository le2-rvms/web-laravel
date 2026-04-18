<?php

namespace App\Broadcasting;

use App\Models\Admin\Admin;
use App\Models\Iot\IotDevice;

class GpsDeviceChannel
{
    public function join(Admin $user, string $companyId, string $terminalId): bool
    {
        if (
            $companyId !== (string) config('app.company_id')
            || !$user->can('GpsData::read')
        ) {
            return false;
        }

        return IotDevice::query()
            ->where('terminal_id', $terminalId)
            ->exists()
        ;
    }
}
