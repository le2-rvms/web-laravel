<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\VehicleController;
use App\Models\Vehicle\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class VehicleControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([VehicleController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateOk(): void
    {
        $resp = $this->getJson(action([VehicleController::class, 'create']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testStoreValidationError(): void
    {
        $payload = [];
        $resp    = $this->postJson(action([VehicleController::class, 'store']), $payload);
        $resp->assertStatus(422);
    }

    public function testShowNotFound(): void
    {
        $resp = $this->getJson(action([VehicleController::class, 'show'], ['vehicle' => 0]));
        $resp->assertStatus(404);
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(VehicleController::class);
        $vehicle    = new Vehicle(['ve_id' => 1]);

        $response = $controller->edit($vehicle);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(VehicleController::class);
        $vehicle    = new Vehicle(['ve_id' => 1]);

        $response = $controller->destroy($vehicle);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(VehicleController::class);
        $vehicle    = new Vehicle(['ve_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $vehicle);
    }

    public function testUploadRequiresFileAndFieldName(): void
    {
        $resp = $this->postJson(action([VehicleController::class, 'upload']), []);
        $resp->assertStatus(422);

        // even with wrong field_name should be 422
        $resp = $this->postJson(action([VehicleController::class, 'upload']), [
            'field_name' => 'invalid_field',
        ]);
        $resp->assertStatus(422);
    }
}
