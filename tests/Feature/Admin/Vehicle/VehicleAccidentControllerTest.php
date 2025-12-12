<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\VehicleService\VehicleAccidentController;
use App\Models\Vehicle\VehicleAccident;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class VehicleAccidentControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([VehicleAccidentController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(VehicleAccidentController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testsaleContractsOptionRequiresVeId(): void
    {
        $resp = $this->getJson(action([VehicleAccidentController::class, 'saleContractsOption']));
        $resp->assertStatus(422);
    }

    public function testUploadValidation(): void
    {
        $resp = $this->postJson(action([VehicleAccidentController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([VehicleAccidentController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(VehicleAccidentController::class);
        $accident   = new VehicleAccident(['va_id' => 1]);

        $response = $controller->edit($accident);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(VehicleAccidentController::class);
        $accident   = new VehicleAccident(['va_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $accident);
    }

    public function testShowReturnsResponse(): void
    {
        $controller = app(VehicleAccidentController::class);
        $accident   = new VehicleAccident(['va_id' => 1]);

        $response = $controller->show($accident);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(VehicleAccidentController::class);
        $accident   = new VehicleAccident(['va_id' => 1]);

        $response = $controller->destroy($accident);

        $this->assertSame(200, $response->getStatusCode());
    }
}
