<?php

declare(strict_types=1);

use Pg\Router\RouteResult;
use PHPUnit\Framework\TestCase;

class RouteResultTest extends TestCase
{
    public function testFromRouteSuccessSetsProperties()
    {
        $route = $this->createMock(\Pg\Router\Route::class);
        $route->method('getName')->willReturn('test.route');
        $attributes = ['id' => 123];

        /** @var \Pg\Router\Route $route */
        $result = RouteResult::fromRouteSuccess($route, $attributes);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame($route, $result->getMatchedRoute());
        $this->assertSame($attributes, $result->getMatchedAttributes());
        $this->assertSame('test.route', $result->getMatchedRouteName());
        $this->assertNull($result->getAllowedMethods());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testFromRouteFailureNotFound()
    {
        $result = RouteResult::fromRouteFailure();

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->getMatchedRoute());
        $this->assertNull($result->getMatchedRouteName());
        $this->assertNull($result->getAllowedMethods());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testFromRouteFailureMethodNotAllowed()
    {
        $allowed = ['GET', 'POST'];
        $result = RouteResult::fromRouteFailure($allowed);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->getMatchedRoute());
        $this->assertNull($result->getMatchedRouteName());
        $this->assertSame($allowed, $result->getAllowedMethods());
        $this->assertTrue($result->isMethodFailure());
    }
}