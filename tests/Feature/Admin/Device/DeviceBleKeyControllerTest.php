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
                'terminal_id' => 'DVC001',
            ],
            [
                'dev_name'   => 'TEST-DVC001',
                'company_id' => config('app.company_id'),
                'created_at' => now()->toDateTimeString(),
            ]
        );

        $response = $this->getJson(action([DeviceBleKeyController::class, 'create'], ['iot_device' => 'DVC001']));

        $response->assertOk()
            ->assertJsonPath('data.device_id', 'DVC001')
            ->assertJsonPath('data.k_dev_enc', '2e3e4a30d59cca47a9cdab716e6002b7')
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
