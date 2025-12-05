<?php

namespace Tests\Feature\Admin\Config;

use App\Enum\Vehicle\ScScStatus;
use App\Http\Controllers\Admin\VehicleService\ServiceCenterController;
use App\Models\Admin\Admin;
use App\Models\Vehicle\ServiceCenter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class ServiceCenterControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ServiceCenter::query()->whereLike('sc_name', 'TEST-%')->delete();
    }

    public function testIndexReturnsOkAndStructure(): void
    {
        ServiceCenter::factory()->count(3)->create(['sc_name' => 'TEST-??']);

        $res = $this->getJson(action([ServiceCenterController::class, 'index']));

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
        $res = $this->getJson(action([ServiceCenterController::class, 'create']));

        $res->assertOk();
        $res->assertJsonStructure([
            'data' => ['sc_status'],
            'extra', // 一般含有 Admin::optionsWithRoles()
        ]);

        $data = $res->json('data');
        $this->assertNotNull($data);
        $this->assertArrayHasKey('sc_status', $data);
    }

    public function testStoreCreatesServiceCenter(): void
    {
        list('AdminOptions' => $AdminOptions) = Admin::optionsWithRoles();

        $payload = ServiceCenter::factory()->raw([
            'sc_name'             => 'TEST-??',
            'permitted_admin_ids' => [$AdminOptions->random()['value']],
        ]);

        $res = $this->postJson(action([ServiceCenterController::class, 'store']), $payload);

        $res->assertOk();
        $res->assertJsonStructure(['data' => ['sc_id', 'sc_name', 'sc_status']]);

        // 断言数据库写入
        $id = $res->json('data.sc_id');
        $this->assertNotNull($id);

        $this->assertDatabaseHas('service_centers', [
            'sc_id'   => $id,
            'sc_name' => $payload['sc_name'],
        ]);
    }

    public function testShowReturnsSingleResource(): void
    {
        $vsc = ServiceCenter::factory()->create(['sc_name' => 'TEST-??']);

        $res = $this->getJson(action([ServiceCenterController::class, 'show'], $vsc->sc_id));

        $res->assertOk();
        $res->assertJsonPath('data.sc_id', $vsc->getKey());
    }

    public function testEditReturnsResourceAndExtra(): void
    {
        $vsc = ServiceCenter::factory()->create(['sc_name' => 'TEST-??']);

        $res = $this->getJson(action([ServiceCenterController::class, 'edit'], $vsc->getKey()));

        $res->assertOk();
        $res->assertJsonStructure([
            'data' => ['sc_id', 'sc_name', 'sc_status'],
            'extra', // 一般含有 Admin::optionsWithRoles()
        ]);
    }

    public function testUpdateUpdatesServiceCenter(): void
    {
        $vsc = ServiceCenter::factory()->create([
            'sc_name'   => 'TEST-旧名字',
            'sc_status' => ScScStatus::ENABLED,
        ]);

        $payload = ServiceCenter::factory()->raw([
            'sc_name'   => 'TEST-新名字',
            'sc_status' => ScScStatus::ENABLED,
        ]);

        $res = $this->putJson(action([ServiceCenterController::class, 'update'], $vsc->getKey()), $payload);

        $res->assertOk();
        $res->assertJsonPath('data.sc_name', 'TEST-新名字');

        $this->assertDatabaseHas('service_centers', [
            'sc_id'   => $vsc->getKey(),
            'sc_name' => 'TEST-新名字',
        ]);
    }

    public function testDestroyDeletesServiceCenter(): void
    {
        $vsc = ServiceCenter::factory()->create(['sc_name' => 'TEST-???']);

        $res = $this->deleteJson(action([ServiceCenterController::class, 'destroy'], $vsc->getKey()));
        $res->assertOk();

        $this->assertDatabaseMissing('service_centers', [
            'sc_id' => $vsc->getKey(),
        ]);
    }

    public function testStoreValidationErrors(): void
    {
        // 缺少必填：sc_name/sc_address/contact_name/sc_status
        $payload = [
            // 'sc_name' => '缺失',
            // 'sc_address' => 110100,
            // 'contact_name' => '张三',
            // 'sc_status' => VscStatus::ENABLED,
        ];

        $res = $this->postJson(action([ServiceCenterController::class, 'store']), $payload);

        $res->assertStatus(422);
        $res->assertJsonStructure([
            'errors' => ['sc_name', 'sc_address', 'contact_name', 'sc_status'],
        ]);
    }

    public function testUpdateValidationErrors(): void
    {
        $vsc = ServiceCenter::factory()->create(['sc_name' => 'TEST-???']);

        $payload = ServiceCenter::factory()->raw([
            'sc_name' => '', // 触发 required|string|max:255
        ]);

        $res = $this->putJson(action([ServiceCenterController::class, 'update'], $vsc->getKey()), $payload);

        $res->assertStatus(422);
        $res->assertJsonStructure(['errors' => ['sc_name']]);
    }
}
