<?php

namespace App\Broadcasting;

use App\Models\Admin\Admin;

class GpsDeviceAllChannel
{
    public function join(Admin $user, string $companyId): bool
    {
        return $companyId === (string) config('app.company_id')
            && $user->can('GpsData::read');
    }
}
