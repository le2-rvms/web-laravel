<?php

namespace Tests\Http\Controllers\Mqtt;

use App\Http\Controllers\Iot\Mqtt\EmqxAuthController;
use App\Models\Iot\IotMqttAccount;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * @property IotMqttAccount $device
 *
 * @internal
 */
#[CoversNothing]
#[\AllowDynamicProperties]
class EmqxAuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        IotMqttAccount::query()->whereLike('user_name', 'test-%')->delete();

        $this->device = IotMqttAccount::factory()->create(['user_name' => 'test-123']);
    }

    public function testAuthenticateWithInvalidData()
    {
        $response = $this->postJson(
            action([EmqxAuthController::class, 'authenticate']),
            [
                // Missing username and password
            ]
        );

        $response->assertStatus(400)
            ->assertJson([
                'result'  => 'ignore',
                'message' => 'Validation failed',
            ])
        ;
    }

    public function testAuthenticateWithNonexistentUser()
    {
        $response = $this->postJson(
            action([EmqxAuthController::class, 'authenticate']),
            [
                'username' => 'nonexistent_user',
                'password' => 'invalid_password',
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['result' => 'deny'])
        ;
    }

    public function testAuthenticateWithIncorrectPassword()
    {
        $response = $this->postJson(
            action([EmqxAuthController::class, 'authenticate']),
            [
                'username' => $this->device->user_name,
                'password' => 'wrong_password',
            ]
        );

        $response->assertStatus(200)
            ->assertJson(['result' => 'deny'])
        ;
    }

    public function testAuthenticateWithCorrectCredentials()
    {
        $response = $this->postJson(
            action([EmqxAuthController::class, 'authenticate']),
            [
                'username' => $this->device->user_name,
                'password' => 'password',
            ]
        );

        $response->assertStatus(200)
            ->assertJson([
                'result'       => 'allow',
                'is_superuser' => 'false',
                //                'client_attrs' => [
                //                    'client_id' => null,
                //                    'user_id'   => (string) $user->id,
                //                ],
            ])
        ;
    }

    public function testAuthenticateWithCorrectCredentials2()
    {
        // Create a fake user
        $device = IotMqttAccount::query()->find(1);

        $response = $this->postJson(
            action([EmqxAuthController::class, 'authenticate']),
            [
                'username' => $device->user_name,
                'password' => 'public',
            ]
        );

        $response->assertStatus(200)
            ->assertJson([
                'result'       => 'allow',
                'is_superuser' => 'false',
                //                'client_attrs' => [
                //                    'client_id' => null,
                //                    'user_id'   => (string) $param['id'],
                //                ],
            ])
        ;
    }
}
