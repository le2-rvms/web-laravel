<?php

namespace Tests\Unit;

use App\Services\BleKeyDeriver;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @internal
 */
#[CoversNothing]
class BleKeyDeriverTest extends TestCase
{
    public function testDeriveKDevEncHex(): void
    {
        config([
            'ble.master_secret_hex'  => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
            'ble.derive_salt'        => 'BLE_DERIVE_SALT_V1',
            'ble.derive_info_prefix' => 'ble-symm-v1|',
        ]);

        $this->assertSame(
            'ced5e8c8c071e5dc6c43de12dbc665c0',
            BleKeyDeriver::deriveKDevEncHex('  DVC001 ')
        );
    }

    public function testDeriveKDevEncHexShouldNormalizeDeviceId(): void
    {
        config([
            'ble.master_secret_hex'  => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
            'ble.derive_salt'        => 'BLE_DERIVE_SALT_V1',
            'ble.derive_info_prefix' => 'ble-symm-v1|',
        ]);

        $this->assertSame(
            BleKeyDeriver::deriveKDevEncHex('DVC001'),
            BleKeyDeriver::deriveKDevEncHex('dvc001')
        );
    }

    public function testDeriveKDevEncHexShouldThrowWhenMasterSecretInvalid(): void
    {
        config([
            'ble.master_secret_hex' => '1234',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BLE_MASTER_SECRET_HEX 必须是 64 位 hex');

        BleKeyDeriver::deriveKDevEncHex('DVC001');
    }

    public function testDeriveKDevEncHexWithEmptyDeviceIdAsCommonMode(): void
    {
        config([
            'ble.master_secret_hex'  => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
            'ble.derive_salt'        => 'BLE_DERIVE_SALT_V1',
            'ble.derive_info_prefix' => 'ble-symm-v1|',
        ]);

        $this->assertSame(
            'ae5ac8e64fd6ff18d01d5f85b5b3d0fb',
            BleKeyDeriver::deriveKDevEncHex('')
        );
    }
}
