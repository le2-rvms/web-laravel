<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\VehicleModelController;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class VehicleModelControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([VehicleModelController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testStoreValidationError(): void
    {
        // 缺少必填字段 vm_brand_name、vm_model_name、vm_status
        $resp = $this->postJson(action([VehicleModelController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testShowNotFound(): void
    {
        $resp = $this->getJson(action([VehicleModelController::class, 'show'], ['vehicle_model' => 0]));
        $resp->assertStatus(404);
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(VehicleModelController::class);

        $response = $controller->create();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(VehicleModelController::class);
        $model      = new VehicleModel(['vm_id' => 1]);

        $response = $controller->edit($model);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(VehicleModelController::class);
        $model      = new VehicleModel(['vm_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $model);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(VehicleModelController::class);
        $model      = new VehicleModel(['vm_id' => 1]);

        $response = $controller->destroy($model);

        $this->assertSame(200, $response->getStatusCode());
    }
}
