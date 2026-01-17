<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GpsPositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $terminalId,
        public readonly string $datetime,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $speed = null,
        public readonly ?float $direction = null,
        public readonly ?float $altitude = null,
        public readonly string $source = 'pgsql_listen',
    ) {}

    public function broadcastOn(): array
    {
        // 同时推送单设备与全量频道，保证单设备/全图监控复用同一事件流。
        return [
            new PrivateChannel('gps.device.'.$this->terminalId),
            new PrivateChannel('gps.device.all'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GpsPositionUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'terminal_id' => $this->terminalId,
            'datetime'    => $this->datetime,
            'latitude'    => $this->latitude,
            'longitude'   => $this->longitude,
            'speed'       => $this->speed,
            'direction'   => $this->direction,
            'altitude'    => $this->altitude,
            'coord_sys'   => 'GCJ02',
            'source'      => $this->source,
            'meta'        => [
                'trace_id' => (string) Str::uuid(),
            ],
        ];
    }
}
