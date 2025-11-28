<?php

function fake_current_period_d($format = 'Y-m-d', $modify = null): string
{
    $base = strtotime(sprintf('%d month', config('setting.gen.month.current') + config('setting.gen.month.offset')));
    $p1   = date('Y-m-01', $base);
    $p2   = date('Y-m-t', $base);

    $date = fake()->dateTimeBetween($p1, $p2);

    if ($modify) {
        $date->modify($modify);
    }

    return $date->format($format);
}
function fake_current_period_dt($format = 'Y-m-d H:i:s'): string
{
    $base = strtotime(sprintf('%d month', config('setting.gen.month.current') + config('setting.gen.month.offset')));
    $p1   = date('Y-m-01', $base);
    $p2   = date('Y-m-t', $base);

    return fake()->dateTimeBetween($p1, $p2)->format($format);
}

function fake_many_photos(int $size = 2): array
{
    $arr = fake_one_photo();

    return array_fill(0, $size, $arr);
}

function fake_one_photo(): array
{
    return [
        'name'    => '__.png',
        'size'    => 223989,
        'path_'   => 'mock/__.png',
        'extname' => 'png',
    ];
}
