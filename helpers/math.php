<?php

function math_array_bcadd(int $scale = 2, ...$numbers): string
{
    return array_reduce(
        $numbers,
        fn (string $carry, $item) => bcadd($carry, (string) $item, $scale),
        '0'
    );
}
