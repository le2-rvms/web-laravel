<?php

namespace Tests\Unit;

use App\Events\GpsPositionUpdated;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class GpsPositionUpdatedTest extends TestCase
{
    public function testBroadcastChannelsIncludeDeviceAndAll(): void
    {
        $event = new GpsPositionUpdated(
            terminalId: '013912345678',
            datetime: '2025-01-01T12:00:00+00:00',
            latitude: 30.1,
            longitude: 120.2,
            speed: 12.3,
            direction: 45.6,
            altitude: 7.8,
            source: 'pgsql_listen'
        );

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);

        $names = array_map(static fn ($channel) => $channel->name, $channels);

        $this->assertEqualsCanonicalizing([
            'private-gps.device.013912345678',
            'private-gps.device.all',
        ], $names);
    }

    public function testBroadcastPayloadStructure(): void
    {
        $event = new GpsPositionUpdated(
            terminalId: '013912345678',
            datetime: '2025-01-01T12:00:00+00:00',
            latitude: 30.1,
            longitude: 120.2,
            speed: 12.3,
            direction: 45.6,
            altitude: 7.8,
            source: 'pgsql_listen'
        );

        $payload = $event->broadcastWith();

        $this->assertSame('013912345678', $payload['terminal_id']);
        $this->assertSame('2025-01-01T12:00:00+00:00', $payload['datetime']);
        $this->assertSame(30.1, $payload['latitude']);
        $this->assertSame(120.2, $payload['longitude']);
        $this->assertSame(12.3, $payload['speed']);
        $this->assertSame(45.6, $payload['direction']);
        $this->assertSame(7.8, $payload['altitude']);
        $this->assertSame('GCJ02', $payload['coord_sys']);
        $this->assertSame('pgsql_listen', $payload['source']);
        $this->assertIsString($payload['meta']['trace_id']);
        $this->assertNotSame('', $payload['meta']['trace_id']);
    }
}
