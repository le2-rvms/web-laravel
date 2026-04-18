<?php

namespace Tests\Feature\Admin\Device;

use App\Http\Controllers\Admin\Device\DeviceBleKeyController;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class DeviceBleKeyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateReturnsDeviceKeyWhenDeviceExists(): void
    {
        DB::connection('timescaledb')->table('devices')->updateOrInsert(
            [
                'terminal_id' => '2510010001',
            ],
            [
                'dev_name'   => 'TEST-2510010001',
                'company_id' => config('app.company_id'),
                'created_at' => now()->toDateTimeString(),
            ]
        );

        $response = $this->getJson(action([DeviceBleKeyController::class, 'create'], ['iot_device' => '2510010001']));

        $response->assertOk()
            ->assertJsonPath('data.device_id', '2510010001')
            ->assertJsonPath('data.terminal_no', '2510010001')
            ->assertJsonPath('data.aes_key_hex', '0b2713097a4f8689289dd58d7d31f0a1')
        ;
    }

    public function testCreateRejectsWhenDeviceNotBelongToCompany(): void
    {
        DB::connection('timescaledb')->table('devices')->updateOrInsert([
            'terminal_id' => 'DVC999',
        ], ['dev_name'   => 'TEST-DVC999',
            'company_id' => 'other-company',
            'created_at' => now()->toDateTimeString(), ]);

        $response = $this->getJson(action([DeviceBleKeyController::class, 'create'], ['iot_device' => 'DVC999']));

        $response->assertStatus(404);
    }
}
