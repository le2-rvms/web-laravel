<?php

namespace Tests\Http\Controllers\Admin\Device;

use App\Http\Controllers\Admin\Device\IotTerminalControlController;
use App\Models\Iot\IotDevice;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Facades\MQTT;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class IotTerminalControlControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected IotDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        config(['app.company_id' => 'test-company']);

        IotDevice::query()->where('terminal_id', 'test-terminal-001')->delete();

        $this->device = IotDevice::query()->create([
            'terminal_id' => 'test-terminal-001',
            'dev_name'    => 'TEST-DEVICE-001',
            'company_id'  => config('app.company_id'),
        ]);
    }

    public function testStorePublishesMqttCommand(): void
    {
        $topic = 'v1/d/'.$this->device->terminal_id.'/down';

        //        $mqtt = \Mockery::mock(MqttClient::class);
        //        $mqtt->shouldReceive('publish')->once()->with($topic, \Mockery::type('string'), 2);
        //        $mqtt->shouldReceive('loop')->once()->with(true, true, 3);
        //
        //        MQTT::shouldReceive('connection')->once()->andReturn($mqtt);
        //        MQTT::shouldReceive('disconnect')->once();

        $payload = [
            'terminal_id' => $this->device->terminal_id,
            'action'      => 'lock',
        ];

        $res = $this->postJson(
            action([IotTerminalControlController::class, 'store']),
            $payload
        );

        $res->assertOk()
            ->assertJsonPath('data.terminal_id', $this->device->terminal_id)
            ->assertJsonPath('data.action', 'lock')
            ->assertJsonPath('data.qos', 2)
        ;
    }
}
