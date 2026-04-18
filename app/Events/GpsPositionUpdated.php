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
        // 频道显式带租户标识，确保广播与订阅都在同一 company_id 命名空间内。
        $companyId = $this->broadcastCompanyId();

        return [
            new PrivateChannel('gps.company.'.$companyId.'.device.'.($this->payload['terminal_id'] ?? '')),
            new PrivateChannel('gps.company.'.$companyId.'.device.all'),
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
                'coord_sys' => $this->payload['coord_sys'] ?? 'GCJ02',
                'source'    => $this->source,
                'meta' => [
                    'source'   => $this->source,
                    'trace_id' => (string) Str::uuid(),
                ],
            ];
    }

    protected function broadcastCompanyId(): string
    {
        return (string) ($this->payload['tenant_id'] ?? config('app.company_id'));
    }
}
