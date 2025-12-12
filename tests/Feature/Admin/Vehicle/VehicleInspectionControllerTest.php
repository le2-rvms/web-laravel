<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Enum\Vehicle\ViInspectionType;
use App\Http\Controllers\Admin\Vehicle\VehicleInspectionController;
use App\Models\Vehicle\VehicleInspection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
class VehicleInspectionControllerTest extends BaseVehicleControllerTestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([VehicleInspectionController::class, 'index']));
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testCreateReturnsResponse(): void
    {
        $controller = app(VehicleInspectionController::class);
        $response   = $controller->create(Request::create('/', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSaleContractsOptionRequiresType(): void
    {
        // 缺少 inspection_type
        $resp = $this->getJson(action([VehicleInspectionController::class, 'saleContractsOption']));
        $resp->assertStatus(422);

        // 正确的 inspection_type 参数
        $resp = $this->getJson(action([VehicleInspectionController::class, 'saleContractsOption']).'?inspection_type='.ViInspectionType::SC_DISPATCH);
        $resp->assertStatus(200);
        $this->assertCommonJsonStructure($resp->json());
    }

    public function testUploadValidationError(): void
    {
        $resp = $this->postJson(action([VehicleInspectionController::class, 'upload']), []);
        $resp->assertStatus(422);
    }

    public function testDocNotFoundWhenInspectionMissing(): void
    {
        $url  = action([VehicleInspectionController::class, 'doc'], ['vehicle_inspection' => -1]);
        $resp = $this->getJson($url.'?mode=pdf&dt_id=0');
        $resp->assertStatus(404);
    }

    public function testShowReturnsResponse(): void
    {
        $controller = app(VehicleInspectionController::class);
        $inspection = new VehicleInspection(['vi_id' => 1]);

        $response = $controller->show($inspection);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testEditReturnsResponse(): void
    {
        $controller = app(VehicleInspectionController::class);
        $inspection = new VehicleInspection(['vi_id' => 1]);

        $response = $controller->edit($inspection);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(VehicleInspectionController::class);
        $inspection = new VehicleInspection(['vi_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $inspection);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(VehicleInspectionController::class);
        $inspection = new VehicleInspection(['vi_id' => 1]);

        $response = $controller->destroy($inspection);

        $this->assertSame(200, $response->getStatusCode());
    }
}
