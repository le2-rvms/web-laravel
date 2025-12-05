<?php

namespace Tests\Http\Controllers\Admin\Device;

use App\Http\Controllers\Admin\Device\IotDeviceBindingController;
use App\Models\Admin\Admin;
use App\Models\Iot\IotDevice;
use App\Models\Iot\IotDeviceBinding;
use App\Models\Vehicle\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property IotDevice $device
 * @property Admin     $admin
 * @property Vehicle   $vehicle
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class DeviceBindingControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Admin::query()->whereLike('name', 'test-%')->delete();
        $this->admin = Admin::factory()->create([
            'name' => 'test-admin',
        ]);

        IotDeviceBinding::query()
            ->whereHas('Vehicle', function (Builder $q) {
                $q->whereLike('plate_no', 'TEST-%');
            })
            ->delete()
        ;

        IotDevice::query()->whereLike('device_code', 'test-%')->delete();
        $this->device = IotDevice::factory()->create(['device_code' => 'test-123']);

        Vehicle::query()->whereLike('plate_no', 'TEST-%')->delete();
        $this->vehicle = Vehicle::factory()->create(['plate_no' => 'TEST-004']);

        $this->deviceBinding = IotDeviceBinding::factory()->create([
            'd_id'         => $this->device->getKey(),
            've_id'        => $this->vehicle->getKey(),
            'processed_by' => $this->admin->getKey(),
            'db_start_at'  => now()->subDays(3),
            'db_end_at'    => now()->subDays(2),
        ]);
    }

    /** 基础的分页列表 */
    public function testIndexReturnsPaginatedList(): void
    {
        $res = $this->getJson(
            action([IotDeviceBindingController::class, 'index']),
        );

        $res->assertOk()
            ->assertJson([
                'data' => [],
            ])
        ;
    }

    /** 展示详情 */
    public function testShowReturnsOneRecord(): void
    {
        $res = $this->getJson(
            action([IotDeviceBindingController::class, 'show'], [$this->deviceBinding]),
        );

        $res->assertOk()
            ->assertJson(
                [
                    'data' => [
                        'db_id' => $this->deviceBinding->getKey(),
                    ],
                ]
            )
        ;
    }

    /** 创建成功（无结束时间，且当前设备没有其他未结束绑定） */
    public function testStoreCreatesBindingSuccessfullyWhenNoOtherOpenBinding(): void
    {
        $this->deviceBinding->delete();

        $payload = IotDeviceBinding::factory()
            ->for($this->device, 'Device')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->admin, 'ProcessedBy')
            ->raw()
        ;

        $res = $this->postJson(
            action([IotDeviceBindingController::class, 'store']),
            $payload
        );

        $res->assertOk()
            ->assertJson([
                'data' => [
                    'd_id'  => $payload['d_id'],
                    've_id' => $payload['ve_id'],
                ],
            ])
        ;

        $this->assertDatabaseHas((new IotDeviceBinding())->getTable(), [
            'd_id'         => $payload['d_id'],
            've_id'        => $payload['ve_id'],
            'processed_by' => $payload['processed_by'],
            'db_start_at'  => $payload['db_start_at'],
            'db_end_at'    => $payload['db_end_at'],
        ]);
    }

    /** 违反“同一设备仅允许一个未结束绑定”的业务规则（应报错 422） */
    public function testStoreRejectsWhenAnotherOpenBindingExistsForSameDevice(): void
    {
        // 先制造一个未结束的
        IotDeviceBinding::factory()
            ->for($this->device, 'Device')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->admin, 'ProcessedBy')
            ->create([
                'db_end_at' => null,
            ])
        ;

        // 又想新建一个未结束 => 不允许
        $payload = IotDeviceBinding::factory()
            ->for($this->device, 'Device')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->admin, 'ProcessedBy')
            ->raw([
                'db_end_at' => null,
            ])
        ;

        $res = $this->postJson(
            action([IotDeviceBindingController::class, 'store']),
            $payload
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['db_end_at'])
        ;
    }

    /**
     * 理想预期：当传了 end_at 时，不应触发“未结束唯一性”的校验。
     */
    public function testUpdateShouldAllowWhenEndAtPresentButExposesTypoBug(): void
    {
        // 已有一个未结束
        IotDeviceBinding::factory()
            ->for($this->device, 'Device')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->admin, 'ProcessedBy')
            ->create([
                'db_end_at' => null,
            ])
        ;

        // 尝试给“另一次新建/更新”传入 end_at：理论上应允许
        $payload
            = IotDeviceBinding::factory()
                ->for($this->device, 'Device')
                ->for($this->vehicle, 'Vehicle')
                ->for($this->admin, 'ProcessedBy')
                ->raw()
        ;

        // 这里用“创建”来模拟新增另一个绑定
        $res = $this->postJson(
            action([IotDeviceBindingController::class, 'store']),
            $payload
        );

        $res->assertOk();
    }

    /** db_end_at 必须晚于 db_start_at */
    public function testStoreRejectsWhenEndBeforeStart(): void
    {
        $payload = IotDeviceBinding::factory()
            ->for($this->device, 'Device')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->admin, 'ProcessedBy')
            ->raw([
                'db_start_at' => now()->toDateTimeString(),
                'db_end_at'   => now()->subDay()->toDateTimeString(),
            ])
        ;

        $res = $this->postJson(
            action([IotDeviceBindingController::class, 'store']),
            $payload
        );

        $res->assertStatus(422)->assertJsonValidationErrors(['db_end_at']);
    }

    /** d_id / ve_id 必须存在 */
    public function testStoreRejectsWhenForeignModelsDoNotExist(): void
    {
        $payload
            = IotDeviceBinding::factory()
                ->for($this->device, 'Device')
                ->for($this->vehicle, 'Vehicle')
                ->for($this->admin, 'ProcessedBy')
                ->raw(
                    [
                        'd_id'  => 'non-exist',
                        've_id' => 'non-exist',
                    ]
                )
        ;

        $res = $this->postJson(
            action([IotDeviceBindingController::class, 'store']),
            $payload
        );

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['d_id', 've_id'])
        ;
    }

    /** 更新成功（将某条补上结束时间） */
    public function testUpdateSuccessfullySetsEndTime(): void
    {
        $binding = IotDeviceBinding::factory()
            ->for($this->device, 'Device')
            ->for($this->vehicle, 'Vehicle')
            ->for($this->admin, 'ProcessedBy')
            ->create([
                'db_start_at' => now()->subDays(5),
                'db_end_at'   => null,
            ])
        ;

        $payload = array_merge($binding->getRawOriginal(), [
            'db_end_at' => now()->toDateTimeString(),
        ]);

        $res = $this->patchJson(
            action([IotDeviceBindingController::class, 'update'], [$binding]),
            $payload
        );

        $res->assertOk()
            ->assertJsonPath('data.db_end_at', $payload['db_end_at'])
        ;
        $this->assertDatabaseHas((new IotDeviceBinding())->getTable(), [
            'db_id'     => $binding->getKey(),
            'db_end_at' => $payload['db_end_at'],
        ]);
    }

    /** 删除成功 */
    public function testDestroyDeletesRecord(): void
    {
        $this->assertDatabaseHas((new IotDeviceBinding())->getTable(), [
            $this->deviceBinding->getKeyName() => $this->deviceBinding->getKey(),
        ]);

        $res = $this->deleteJson(
            action([IotDeviceBindingController::class, 'destroy'], [$this->deviceBinding]),
        );

        $res->assertOk();
        $this->assertDatabaseMissing((new IotDeviceBinding())->getTable(), [
            $this->deviceBinding->getKeyName() => $this->deviceBinding->getKey(),
        ]);
    }
}
