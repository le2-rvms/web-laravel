<?php

namespace Tests\Feature\Admin\Config;

use App\Enum\Vehicle\VcVcStatus;
use App\Http\Controllers\Admin\VehicleService\VehicleCenterController;
use App\Models\Admin\Admin;
use App\Models\Vehicle\VehicleCenter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class VehicleCenterControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        VehicleCenter::query()->whereLike('vc_name', 'TEST-%')->delete();
    }

    public function testIndexReturnsOkAndStructure(): void
    {
        VehicleCenter::factory()->count(3)->create(['vc_name' => 'TEST-??']);

        $res = $this->getJson(action([VehicleCenterController::class, 'index']));

        $res->assertOk();
        // 依据你的统一响应结构调整以下断言键名
        $res->assertJsonStructure([
            'data' => [
                'data',
                'current_page', 'per_page', 'total',
            ],
            'extra',
        ]);
    }

    public function testCreateReturnsDefaultDataAndExtra(): void
    {
        $res = $this->getJson(action([VehicleCenterController::class, 'create']));

        $res->assertOk();
        $res->assertJsonStructure([
            'data' => ['vc_status'],
            'extra', // 一般含有 Admin::optionsWithRoles()
        ]);

        $data = $res->json('data');
        $this->assertNotNull($data);
        $this->assertArrayHasKey('vc_status', $data);
    }

    public function testStoreCreatesVehicleCenter(): void
    {
        list('AdminOptions' => $AdminOptions) = Admin::optionsWithRoles();

        $payload = VehicleCenter::factory()->raw([
            'vc_name'      => 'TEST-??',
            'vc_permitted' => [$AdminOptions->random()['value']],
        ]);

        $res = $this->postJson(action([VehicleCenterController::class, 'store']), $payload);

        $res->assertOk();
        $res->assertJsonStructure(['data' => ['vc_id', 'vc_name', 'vc_status']]);

        // 断言数据库写入
        $id = $res->json('data.vc_id');
        $this->assertNotNull($id);

        $this->assertDatabaseHas('vehicle_centers', [
            'vc_id'   => $id,
            'vc_name' => $payload['vc_name'],
        ]);
    }

    public function testShowReturnsSingleResource(): void
    {
        $vsc = VehicleCenter::factory()->create(['vc_name' => 'TEST-??']);

        $res = $this->getJson(action([VehicleCenterController::class, 'show'], $vsc->vc_id));

        $res->assertOk();
        $res->assertJsonPath('data.vc_id', $vsc->getKey());
    }

    public function testEditReturnsResourceAndExtra(): void
    {
        $vsc = VehicleCenter::factory()->create(['vc_name' => 'TEST-??']);

        $res = $this->getJson(action([VehicleCenterController::class, 'edit'], $vsc->getKey()));

        $res->assertOk();
        $res->assertJsonStructure([
            'data' => ['vc_id', 'vc_name', 'vc_status'],
            'extra', // 一般含有 Admin::optionsWithRoles()
        ]);
    }

    public function testUpdateUpdatesVehicleCenter(): void
    {
        $vsc = VehicleCenter::factory()->create([
            'vc_name'   => 'TEST-旧名字',
            'vc_status' => VcVcStatus::ENABLED,
        ]);

        $payload = VehicleCenter::factory()->raw([
            'vc_name'   => 'TEST-新名字',
            'vc_status' => VcVcStatus::ENABLED,
        ]);

        $res = $this->putJson(action([VehicleCenterController::class, 'update'], $vsc->getKey()), $payload);

        $res->assertOk();
        $res->assertJsonPath('data.vc_name', 'TEST-新名字');

        $this->assertDatabaseHas('vehicle_centers', [
            'vc_id'   => $vsc->getKey(),
            'vc_name' => 'TEST-新名字',
        ]);
    }

    public function testDestroyDeletesVehicleCenter(): void
    {
        $vsc = VehicleCenter::factory()->create(['vc_name' => 'TEST-???']);

        $res = $this->deleteJson(action([VehicleCenterController::class, 'destroy'], $vsc->getKey()));
        $res->assertOk();

        $this->assertDatabaseMissing('vehicle_centers', [
            'vc_id' => $vsc->getKey(),
        ]);
    }

    public function testStoreValidationErrors(): void
    {
        // 缺少必填：vc_name/vc_address/contact_name/vc_status
        $payload = [
            // 'vc_name' => '缺失',
            // 'vc_address' => 110100,
            // 'contact_name' => '张三',
            // 'vc_status' => VscStatus::ENABLED,
        ];

        $res = $this->postJson(action([VehicleCenterController::class, 'store']), $payload);

        $res->assertStatus(422);
        $res->assertJsonStructure([
            'errors' => ['vc_name', 'vc_address', 'contact_name', 'vc_status'],
        ]);
    }

    public function testUpdateValidationErrors(): void
    {
        $vsc = VehicleCenter::factory()->create(['vc_name' => 'TEST-???']);

        $payload = VehicleCenter::factory()->raw([
            'vc_name' => '', // 触发 required|string|max:255
        ]);

        $res = $this->putJson(action([VehicleCenterController::class, 'update'], $vsc->getKey()), $payload);

        $res->assertStatus(422);
        $res->assertJsonStructure(['errors' => ['vc_name']]);
    }
}
