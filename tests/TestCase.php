<?php

namespace Tests;

use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 遇到异常直接抛出，便于定位问题
        //        $this->withoutExceptionHandling();

        if (!Schema::hasTable('admins')) {
            if (str_starts_with(static::class, 'Tests\\Feature\\')) {
                $this->markTestSkipped('Feature tests require the legacy business database.');
            }

            return;
        }

        $admin = Admin::query()->where('name', '=', config('setting.super_user.name'))->first();

        if ($admin) {
            $this->actingAs($admin);
        }
    }
}
