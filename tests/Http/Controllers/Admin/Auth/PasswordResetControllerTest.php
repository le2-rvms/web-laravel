<?php

namespace Tests\Http\Controllers\Admin\Auth;

use App\Enum\Admin\AUserType;
use App\Http\Controllers\Admin\_\PasswordResetController;
use App\Models\Admin\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\NoAuthTestCase;

/**
 * @internal
 */
#[CoversNothing]
class PasswordResetControllerTest extends NoAuthTestCase
{
    public function testUpdateAcceptsStoredCodeAndResetsPassword(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('数据库不可用，跳过 PasswordResetController 集成测试。');
        }

        $email = 'pwd-reset-test@example.com';

        $admin = Admin::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'        => 'pwd-reset-test',
                'password'    => 'old-password-123',
                'a_user_type' => AUserType::COMMON,
            ]
        );

        $cacheKey = 'password_reset_code:'.$email;
        Cache::put($cacheKey, '1234', 60);

        $response = $this->putJson(
            action([PasswordResetController::class, 'update'], ['password' => 1]),
            [
                'email'                 => $email,
                'code'                  => '1234',
                'password'              => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]
        );

        $response->assertOk()
            ->assertJsonFragment(['message' => '密码已重置'])
        ;

        $admin->refresh();
        $this->assertTrue(Hash::check('new-password-123', $admin->password));
        $this->assertFalse(Cache::has($cacheKey));
    }
}
