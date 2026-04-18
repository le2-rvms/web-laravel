<?php

function deepDiff(array $after, array $before): array
{
    $diff = [];
    foreach ($after as $key => $value) {
        // key 不存在或值不同（包括数组 vs 数值不同时）
        if (!array_key_exists($key, $before)) {
            $diff[$key] = $value;
        } elseif (is_array($value) && is_array($before[$key])) {
            // 都是数组，递归对比
            $sub = deepDiff($value, $before[$key]);
            if (!empty($sub)) {
                $diff[$key] = $sub;
            }
        } elseif ($value !== $before[$key]) {
            // 普通值不相等
            $diff[$key] = $value;
        }
    }

    return $diff;
}

function shallow_diff($v1, $v2): int
{
    // 如果都是数组，用 “===” 比较整个数组
    if (is_array($v1) && is_array($v2)) {
        return $v1 === $v2 ? 0 : 1;
    }

    // 否则，严格比较标量
    return $v1 === $v2 ? 0 : 1;
}
