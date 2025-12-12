<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\VehicleService\VehicleMaintenanceController;
use App\Models\Vehicle\VehicleMaintenance;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class VehicleMaintenanceControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([VehicleMaintenanceController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(VehicleMaintenanceController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSaleContractsOptionRequiresVeId(): void
    {
        $resp = $this->getJson(action([VehicleMaintenanceController::class, 'saleContractsOption']));
        $resp->assertStatus(422);
    }

    public function testUploadValidation(): void
    {
        $resp = $this->postJson(action([VehicleMaintenanceController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([VehicleMaintenanceController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testEditReturnsResponse(): void
    {
        $controller  = app(VehicleMaintenanceController::class);
        $maintenance = new VehicleMaintenance(['vm_id' => 1]);

        $response = $controller->edit($maintenance);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller  = app(VehicleMaintenanceController::class);
        $maintenance = new VehicleMaintenance(['vm_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $maintenance);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller  = app(VehicleMaintenanceController::class);
        $maintenance = new VehicleMaintenance(['vm_id' => 1]);

        $response = $controller->destroy($maintenance);

        $this->assertSame(200, $response->getStatusCode());
    }
}
