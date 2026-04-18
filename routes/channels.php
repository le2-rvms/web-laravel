<?php

use App\Broadcasting\GpsDeviceAllChannel;
use App\Broadcasting\GpsDeviceChannel;
use Illuminate\Support\Facades\Broadcast;

// 本租户下所有设备
Broadcast::channel('gps.company.{companyId}.device.all', GpsDeviceAllChannel::class);

// 本租户下某个设备
Broadcast::channel('gps.company.{companyId}.device.{terminalId}', GpsDeviceChannel::class);
