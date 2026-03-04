<?php

namespace Tests\Unit;

use App\Services\BleKeyDeriver;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class BleKeyDeriverTest extends TestCase
{
    public function testDeriveByTerminalNoWithGoldenVector(): void
    {
        $result = BleKeyDeriver::deriveByTerminalNo('2510010001');

        $this->assertSame('2510010001', $result['terminal_no']);
        $this->assertSame('0b2713097a4f8689289dd58d7d31f0a1', $result['aes_key_hex']);
        $this->assertCount(16, $result['aes_key']);
        $this->assertSame([11, 39, 19, 9, 122, 79, 134, 137, 40, 157, 213, 141, 125, 49, 240, 161], $result['aes_key']);
    }

    public function testDeriveByTerminalNoRejectsNonDigit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('terminal_no 仅允许数字字符 0-9');

        BleKeyDeriver::deriveByTerminalNo('ABC123');
    }

    public function testDeriveByTerminalNoRejectsOutOfUint32Range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('terminal_no 数值必须在 uint32 范围内');

        BleKeyDeriver::deriveByTerminalNo('4294967296');
    }
}
