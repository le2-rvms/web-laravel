<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\VehicleViolationController;
use App\Models\Vehicle\VehicleViolation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class VehicleViolationControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([VehicleViolationController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(VehicleViolationController::class);
        $response   = $controller->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([VehicleViolationController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testShowReturnsResponse(): void
    {
        $controller = app(VehicleViolationController::class);
        $violation  = new VehicleViolation(['vv_id' => 1]);

        $response = $controller->show($violation);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(VehicleViolationController::class);
        $violation  = new VehicleViolation(['vv_id' => 1]);

        $response = $controller->edit($violation);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(VehicleViolationController::class);
        $violation  = new VehicleViolation(['vv_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $violation);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(VehicleViolationController::class);
        $violation  = new VehicleViolation(['vv_id' => 1]);

        $response = $controller->destroy($violation);

        $this->assertSame(200, $response->getStatusCode());
    }
}
