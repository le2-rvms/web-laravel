<?php

namespace App\Services;

class BleKeyDeriver
{
    public static function deriveByTerminalNo(string $terminalNo): array
    {
        $normalized = static::normalizeTerminalNo($terminalNo);
        $n          = (int) $normalized;
        $decStr     = str_pad($normalized, 10, '0', STR_PAD_LEFT);
        $hexStr     = strtolower(dechex($n));
        $mixed      = static::mixDecAndHex($decStr, $hexStr);
        $digest     = hash('sha256', $mixed, true);
        $aesKey     = '';

        for ($i = 0; $i < 16; ++$i) {
            $aesKey .= chr(ord($digest[$i]) ^ ord($digest[$i + 16]));
        }

        return [
            'terminal_no' => $normalized,
            'aes_key'     => array_values(unpack('C*', $aesKey)),
            'aes_key_hex' => bin2hex($aesKey),
        ];
    }

    public static function normalizeTerminalNo(string $terminalNo): string
    {
        $terminalNo = trim($terminalNo);

        if ('' === $terminalNo || !preg_match('/^\d+$/', $terminalNo)) {
            throw new \InvalidArgumentException('terminal_no 仅允许数字字符 0-9');
        }

        if (strlen($terminalNo) > 10) {
            throw new \InvalidArgumentException('terminal_no 长度不能超过 10 位');
        }

        $n = (int) $terminalNo;
        if ($n < 0 || $n > 4294967295) {
            throw new \InvalidArgumentException('terminal_no 数值必须在 uint32 范围内');
        }

        return $terminalNo;
    }

    private static function mixDecAndHex(string $decStr, string $hexStr): string
    {
        $mixed  = '';
        $hexLen = strlen($hexStr);

        for ($i = 0; $i < strlen($decStr); ++$i) {
            $mixed .= $decStr[$i];
            if ($i < $hexLen) {
                $mixed .= $hexStr[$i];
            }
        }

        return $mixed;
    }
}
