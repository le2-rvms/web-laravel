<?php

namespace Tests\Feature\Admin\Vehicle;

use App\Http\Controllers\Admin\Vehicle\OneAccountController;
use App\Models\One\OneAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class OneAccountControllerTest extends TestCase
{
    public function testIndexOk(): void
    {
        $resp = $this->getJson(action([OneAccountController::class, 'index']));
        $resp->assertStatus(200);
    }

    public function testStoreValidationError(): void
    {
        $resp = $this->postJson(action([OneAccountController::class, 'store']), []);
        $resp->assertStatus(422);
    }

    public function testShowNotFound(): void
    {
        $resp = $this->getJson(action([OneAccountController::class, 'show'], ['one_account' => 0]));
        $resp->assertStatus(404);
    }

    public function testCreateReturnsResponse(): void
    {
        $resp = $this->getJson(action([OneAccountController::class, 'create']));

        $resp->assertStatus(200);
    }

    public function testEditReturnsResponse(): void
    {
        OneAccount::factory()->create(['']);

        $resp = $this->getJson(action([OneAccountController::class, 'edit'], [1]));

        $resp->assertStatus(200);
    }

    public function testUpdateValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $controller = app(OneAccountController::class);
        $account    = new OneAccount(['oa_id' => 1]);

        $controller->update(Request::create('/', 'PUT', []), $account);
    }

    public function testDestroyReturnsResponse(): void
    {
        $controller = app(OneAccountController::class);
        $account    = new OneAccount(['oa_id' => 1]);

        $response = $controller->destroy($account);

        $this->assertSame(200, $response->getStatusCode());
    }
}
