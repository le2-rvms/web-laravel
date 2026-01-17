<?php

namespace App\Broadcasting;

use App\Models\Admin\Admin;

class GpsDeviceAllChannel
{
    public function join(Admin $user): bool
    {
        return $user->can('GpsData::read');
    }
}
