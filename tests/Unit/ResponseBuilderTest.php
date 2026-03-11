<?php

namespace Tests\Unit;

use App\Http\Responses\ResponseBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\NoAuthTestCase;

/**
 * @internal
 */
#[CoversNothing]
class ResponseBuilderTest extends NoAuthTestCase
{
    public function testWithViewShouldUseExplicitViewName(): void
    {
        $request  = Request::create('/dummy', 'GET');
        $response = (new ResponseBuilder(self::class, $request))
            ->withView('welcome')
            ->respond()
        ;

        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(View::class, $response->original);
        $this->assertSame('welcome', $response->original->name());
    }
}
