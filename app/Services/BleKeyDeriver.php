<?php

namespace App\Services;

class BleKeyDeriver
{
    public static function deriveKDevEncHex(string $deviceId): string
    {
        return bin2hex(static::deriveKDevEncRaw($deviceId));
    }

    public static function deriveKDevEncRaw(string $deviceId): string
    {
        return static::deriveByScopeRaw(static::normalizeDeviceId($deviceId));
    }

    public static function normalizeDeviceId(string $deviceId): string
    {
        return strtolower(trim($deviceId));
    }

    private static function deriveByScopeRaw(string $scope): string
    {
        $masterSecret = static::masterSecretBinary();

        $kDevEncRaw = hash_hkdf(
            'sha256',
            $masterSecret,
            16,
            config('ble.derive_info_prefix').$scope,
            config('ble.derive_salt')
        );

        if (false === $kDevEncRaw || 16 !== strlen($kDevEncRaw)) {
            throw new \InvalidArgumentException('K_dev_enc 派生失败');
        }

        return $kDevEncRaw;
    }

    private static function masterSecretBinary(): string
    {
        $masterSecretHex = trim((string) config('ble.master_secret_hex'));

        if (!preg_match('/^[0-9a-fA-F]{64}$/', $masterSecretHex)) {
            throw new \InvalidArgumentException('BLE_MASTER_SECRET_HEX 必须是 64 位 hex');
        }

        $masterSecret = hex2bin($masterSecretHex);

        if (false === $masterSecret) {
            throw new \InvalidArgumentException('BLE_MASTER_SECRET_HEX 解析失败');
        }

        return $masterSecret;
    }
}
