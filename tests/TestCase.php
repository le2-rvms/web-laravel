<?php

namespace Tests;

use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 遇到异常直接抛出，便于定位问题
        $this->withoutExceptionHandling();

        try {
            $admin = Admin::query()->where('name', '=', config('setting.super_user.name'))->first();
        } catch (\Throwable $e) {
            $admin = null;
        }

        if ($admin) {
            $this->actingAs($admin);
        }
    }
}
