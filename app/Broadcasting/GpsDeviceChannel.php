<?php

namespace App\Broadcasting;

use App\Models\Admin\Admin;
use Illuminate\Support\Facades\DB;

class GpsDeviceChannel
{
    public function join(Admin $user, string $terminalId): bool
    {
        if (!$user->can('GpsData::read')) {
            return false;
        }

        $query = DB::connection('pgsql-iot')
            ->table('gps_devices')
            ->where('terminal_id', $terminalId)
        ;

        $companyId = config('app.company_id');
        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        return $query->exists();
    }
}
