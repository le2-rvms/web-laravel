<?php

namespace Tests\Feature\Admin\Vehicle;

use Tests\TestCase;

abstract class BaseVehicleControllerTestCase extends TestCase
{
    protected function assertCommonJsonStructure(array $payload): void
    {
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('extra', $payload);
        $this->assertArrayHasKey('meta', $payload);
    }
}
