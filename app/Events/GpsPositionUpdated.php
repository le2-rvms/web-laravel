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
        public readonly array $payload,
        public readonly string $source,
    ) {}

    public function broadcastOn(): array
    {
        // 同时推送单设备与全量频道，保证单设备/全图监控复用同一事件流。
        return [
            new PrivateChannel('gps.device.'.($this->payload['terminal_id'] ?? '')),
            new PrivateChannel('gps.device.all'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GpsPositionUpdated';
    }

    public function broadcastWith(): array
    {
        return
            $this->payload
            + [
                'meta' => [
                    'source'   => $this->source,
                    'trace_id' => (string) Str::uuid(),
                ],
            ];
    }
}
