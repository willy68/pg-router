<?php

declare(strict_types=1);

namespace PgTest\Router;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Pg\Router\RouteResult;
use Pg\Router\Route;

class RouteResultTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testFromRouteSuccessCreatesSuccessResult()
    {
        $mockRoute = $this->createMock(Route::class);
        $attributes = ['id' => 123];

        $result = RouteResult::fromRouteSuccess($mockRoute, $attributes);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame($mockRoute, $result->getMatchedRoute());
        $this->assertEquals($attributes, $result->getMatchedAttributes());
    }

    public function testFromRouteFailureNotFound()
    {
        $result = RouteResult::fromRouteFailure();

        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->getMatchedRoute());
        $this->assertNull($result->getAllowedMethods());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testFromRouteFailureMethodNotAllowed()
    {
        $allowed = ['GET', 'POST'];
        $result = RouteResult::fromRouteFailure($allowed);

        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals($allowed, $result->getAllowedMethods());
    }

    /**
     * @throws Exception
     */
    public function testGetMatchedRouteNameReturnsRouteName()
    {
        $mockRoute = $this->createMock(Route::class);
        $mockRoute->method('getName')->willReturn('my_route');

        $result = RouteResult::fromRouteSuccess($mockRoute);

        $this->assertEquals('my_route', $result->getMatchedRouteName());
    }

    public function testGetMatchedRouteNameReturnsNullOnFailure()
    {
        $result = RouteResult::fromRouteFailure();
        $this->assertNull($result->getMatchedRouteName());
    }

    /**
     * @throws Exception
     */
    public function testGetMatchedAttributesReturnsEmptyArrayByDefault()
    {
        $mockRoute = $this->createMock(Route::class);

        $result = RouteResult::fromRouteSuccess($mockRoute);

        $this->assertEquals([], $result->getMatchedAttributes());
    }
}
