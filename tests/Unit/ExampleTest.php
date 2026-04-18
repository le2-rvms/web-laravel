<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function testThatTrueIsTrue(): void
    {
        $this->assertTrue(true);
    }
}
