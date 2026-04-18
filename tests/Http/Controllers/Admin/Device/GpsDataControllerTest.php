<?php

namespace Tests\Http\Controllers\Admin\Device;

use App\Http\Controllers\Admin\Device\GpsDataController;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class GpsDataControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /** 基础的分页列表 */
    public function testHistory(): void
    {
        $res = $this->getJson(
            action(
                [GpsDataController::class, 'history_vehicle'],
                [
                    'vehicle'     => 14,
                    'db_start_at' => '2025-08-13 12:24',
                    'db_end_at'   => '2025-08-13 12:27',
                ]
            ),
        );

        $res->assertOk();
    }

    public function testLatest(): void
    {
        $res = $this->getJson(
            action(
                [GpsDataController::class, 'latest'],
                [
                ]
            ),
        );
        echo json_encode($res->json());
        $res->assertOk();
    }
}
