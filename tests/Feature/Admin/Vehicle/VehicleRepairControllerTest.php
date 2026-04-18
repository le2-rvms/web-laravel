<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\VehicleService\VehicleRepairController;
use App\Models\Vehicle\VehicleRepair;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class VehicleRepairControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([VehicleRepairController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(VehicleRepairController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSaleContractsOptionRequiresVeId(): void
    {
        $resp = $this->getJson(action([VehicleRepairController::class, 'saleContractsOption']));
        $resp->assertStatus(422);
    }

    public function testUploadValidation(): void
    {
        $resp = $this->postJson(action([VehicleRepairController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([VehicleRepairController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(VehicleRepairController::class);
        $repair     = new VehicleRepair(['vr_id' => 1]);

        $response = $controller->edit($repair);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(VehicleRepairController::class);
        $repair     = new VehicleRepair(['vr_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $repair);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(VehicleRepairController::class);
        $repair     = new VehicleRepair(['vr_id' => 1]);

        $response = $controller->destroy($repair);

        $this->assertSame(200, $response->getStatusCode());
    }
}
