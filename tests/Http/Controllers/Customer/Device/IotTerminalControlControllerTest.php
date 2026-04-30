<?php

namespace Tests\Http\Controllers\Customer\Device;

use App\Enum\Iot\TerminalCmd;
use App\Enum\SaleContract\ScStatus;
use App\Enum\Vehicle\VeStatusDispatch;
use App\Enum\Vehicle\VeStatusRental;
use App\Enum\Vehicle\VeStatusService;
use App\Http\Controllers\Customer\TerminalKeyControlController;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Iot\IotDevice;
use App\Models\Iot\IotDeviceBinding;
use App\Models\Sale\SaleContract;
use App\Models\Vehicle\Vehicle;
use App\Models\Vehicle\VehicleModel;
use App\Services\IotTerminalCommandPublisher;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\NoAuthTestCase;

/**
 * @internal
 */
#[CoversNothing]
class IotTerminalControlControllerTest extends NoAuthTestCase
{
    use MockeryPHPUnitIntegration;

    private Admin $admin;

    private Customer $customer;

    private Customer $otherCustomer;

    private VehicleModel $vehicleModel;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admins', 'customers', 'vehicles', 'vehicle_models', 'sale_contracts', 'iot_device_bindings'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped('Customer IoT controller tests require the legacy business database.');
            }
        }

        try {
            if (!Schema::connection('timescaledb')->hasTable('devices')) {
                $this->markTestSkipped('Customer IoT controller tests require the IoT devices database.');
            }
        } catch (\Throwable) {
            $this->markTestSkipped('Customer IoT controller tests require the IoT devices database.');
        }

        config(['app.company_id' => 'test-company']);

        $suffix = Str::upper(Str::random(8));

        $this->admin = Admin::factory()->create([
            'name' => 'test-customer-iot-'.$suffix,
        ]);

        $this->customer = Customer::factory()->create([
            'cu_contact_name'  => 'CUS-IOT-'.$suffix,
            'cu_contact_phone' => '197'.random_int(10000000, 99999999),
        ]);

        $this->otherCustomer = Customer::factory()->create([
            'cu_contact_name'  => 'CUS-IOT-OTHER-'.$suffix,
            'cu_contact_phone' => '196'.random_int(10000000, 99999999),
        ]);

        $this->vehicleModel = VehicleModel::factory()->create([
            'vm_brand_name' => 'TEST-BRAND-'.$suffix,
            'vm_model_name' => 'TEST-MODEL-'.$suffix,
        ]);
    }

    public function testIndexReturnsOnlyCurrentCustomerSignedActiveGpsKeys(): void
    {
        $allowedVehicle = $this->createVehicle('KEY-ALLOW');
        $this->createContract($this->customer, $allowedVehicle, ScStatus::SIGNED);
        $this->createDevice('term-allow', 'gps');
        $this->createBinding($allowedVehicle, 'term-allow');

        $otherVehicle = $this->createVehicle('KEY-OTHER');
        $this->createContract($this->otherCustomer, $otherVehicle, ScStatus::SIGNED);
        $this->createDevice('term-other', 'gps');
        $this->createBinding($otherVehicle, 'term-other');

        $pendingVehicle = $this->createVehicle('KEY-PENDING');
        $this->createContract($this->customer, $pendingVehicle, ScStatus::PENDING);
        $this->createDevice('term-pending', 'gps');
        $this->createBinding($pendingVehicle, 'term-pending');

        $endedVehicle = $this->createVehicle('KEY-ENDED');
        $this->createContract($this->customer, $endedVehicle, ScStatus::SIGNED);
        $this->createDevice('term-ended', 'gps');
        $this->createBinding($endedVehicle, 'term-ended', now()->subDays(4), now()->subDays(2));

        $futureVehicle = $this->createVehicle('KEY-FUTURE');
        $this->createContract($this->customer, $futureVehicle, ScStatus::SIGNED);
        $this->createDevice('term-future', 'gps');
        $this->createBinding($futureVehicle, 'term-future', now()->addDay(), null);

        $nonGpsVehicle = $this->createVehicle('KEY-NONGPS');
        $this->createContract($this->customer, $nonGpsVehicle, ScStatus::SIGNED);
        $this->createDevice('term-nongps', 'ble');
        $this->createBinding($nonGpsVehicle, 'term-nongps');

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(action([TerminalKeyControlController::class, 'index']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.terminal_id', 'term-allow')
            ->assertJsonPath('data.0.device_name', 'DEVICE-term-allow')
            ->assertJsonPath('data.0.vehicle.ve_id', $allowedVehicle->getKey())
            ->assertJsonPath('data.0.vehicle.ve_plate_no', $allowedVehicle->ve_plate_no)
            ->assertJsonPath('data.0.vehicle.vm_brand_name', $this->vehicleModel->vm_brand_name)
            ->assertJsonPath('data.0.sale_contract.sc_id', SaleContract::query()->where('sc_ve_id', $allowedVehicle->getKey())->value('sc_id'))
            ->assertJsonPath('data.0.actions', ['lock', 'unlock', 'beep'])
        ;
    }

    public function testIndexDeduplicatesVehicleTerminalAndUsesLatestContract(): void
    {
        $vehicle  = $this->createVehicle('KEY-DUP');
        $oldOrder = $this->createContract($this->customer, $vehicle, ScStatus::SIGNED);
        $newOrder = $this->createContract($this->customer, $vehicle, ScStatus::SIGNED);

        $this->createDevice('term-dup', 'gps');
        $this->createBinding($vehicle, 'term-dup');

        Sanctum::actingAs($this->customer);

        $response = $this->getJson(action([TerminalKeyControlController::class, 'index']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.terminal_id', 'term-dup')
            ->assertJsonPath('data.0.sale_contract.sc_id', $newOrder->getKey())
        ;

        $this->assertGreaterThan($oldOrder->getKey(), $newOrder->getKey());
    }

    public function testStorePublishesCommandForCurrentCustomerGpsKey(): void
    {
        $vehicle = $this->createVehicle('KEY-POST');
        $this->createContract($this->customer, $vehicle, ScStatus::SIGNED);
        $this->createDevice('term-post', 'gps');
        $this->createBinding($vehicle, 'term-post');

        $publisher = \Mockery::mock(IotTerminalCommandPublisher::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->with('term-post', TerminalCmd::kv['lock'])
            ->andReturn([
                'topic'   => 'v1/d/term-post/down',
                'payload' => [
                    'payload' => [
                        'command_id' => 'cmd-test',
                    ],
                ],
            ])
        ;
        $this->app->instance(IotTerminalCommandPublisher::class, $publisher);

        Sanctum::actingAs($this->customer);

        $response = $this->postJson(action([TerminalKeyControlController::class, 'store']), [
            'terminal_id' => 'term-post',
            'action'      => 'lock',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.terminal_id', 'term-post')
            ->assertJsonPath('data.command_id', 'cmd-test')
            ->assertJsonPath('data.action', 'lock')
            ->assertJsonPath('data.qos', 2)
        ;
    }

    public function testStoreRejectsOtherCustomerOrNonGpsTerminal(): void
    {
        $otherVehicle = $this->createVehicle('KEY-REJ-OTHER');
        $this->createContract($this->otherCustomer, $otherVehicle, ScStatus::SIGNED);
        $this->createDevice('term-rej-other', 'gps');
        $this->createBinding($otherVehicle, 'term-rej-other');

        $nonGpsVehicle = $this->createVehicle('KEY-REJ-NONGPS');
        $this->createContract($this->customer, $nonGpsVehicle, ScStatus::SIGNED);
        $this->createDevice('term-rej-nongps', 'ble');
        $this->createBinding($nonGpsVehicle, 'term-rej-nongps');

        $publisher = \Mockery::mock(IotTerminalCommandPublisher::class);
        $publisher->shouldNotReceive('publish');
        $this->app->instance(IotTerminalCommandPublisher::class, $publisher);

        Sanctum::actingAs($this->customer);

        $this->postJson(action([TerminalKeyControlController::class, 'store']), [
            'terminal_id' => 'term-rej-other',
            'action'      => 'lock',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['terminal_id'])
        ;

        $this->postJson(action([TerminalKeyControlController::class, 'store']), [
            'terminal_id' => 'term-rej-nongps',
            'action'      => 'unlock',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['terminal_id'])
        ;
    }

    private function createVehicle(string $platePrefix): Vehicle
    {
        return Vehicle::factory()->create([
            've_plate_no'        => $platePrefix.'-'.Str::upper(Str::random(6)),
            've_vm_id'           => $this->vehicleModel->getKey(),
            've_status_service'  => VeStatusService::YES,
            've_status_rental'   => VeStatusRental::RENTED,
            've_status_dispatch' => VeStatusDispatch::DISPATCHED,
        ]);
    }

    private function createContract(Customer $customer, Vehicle $vehicle, string $status): SaleContract
    {
        return SaleContract::factory()
            ->for($customer, 'Customer')
            ->for($vehicle, 'Vehicle')
            ->create([
                'sc_no'        => 'KEY-SC-'.Str::upper(Str::random(10)),
                'sc_group_no'  => 'KEY-GROUP-'.Str::upper(Str::random(10)),
                'sc_group_seq' => 1,
                'sc_status'    => $status,
            ])
        ;
    }

    private function createDevice(string $terminalId, string $productKey): IotDevice
    {
        IotDevice::query()->where('terminal_id', '=', $terminalId)->delete();

        return IotDevice::query()->create([
            'terminal_id' => $terminalId,
            'dev_name'    => 'DEVICE-'.$terminalId,
            'company_id'  => config('app.company_id'),
            'product_key' => $productKey,
        ]);
    }

    private function createBinding(
        Vehicle $vehicle,
        string $terminalId,
        ?\DateTimeInterface $startAt = null,
        ?\DateTimeInterface $endAt = null,
    ): IotDeviceBinding {
        return IotDeviceBinding::query()->create([
            'db_terminal_id'  => $terminalId,
            'db_ve_id'        => $vehicle->getKey(),
            'db_start_at'     => ($startAt ?? now()->subDay())->format('Y-m-d H:i:s'),
            'db_end_at'       => $endAt?->format('Y-m-d H:i:s'),
            'db_processed_by' => $this->admin->getKey(),
        ]);
    }
}
