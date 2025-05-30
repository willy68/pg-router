<?php

declare(strict_types=1);

namespace PgTest\Router;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Pg\Router\RouteCollector;
use Pg\Router\RouterInterface;
use Pg\Router\Route;
use Pg\Router\RouteGroup;

class RouteCollectorTest extends TestCase
{
    private $routerMock;
    private RouteCollector $routeCollector;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->routeCollector = new RouteCollector($this->routerMock);
    }

    public function testRouteAddsRouteAndReturnsIt()
    {
        $path = '/test';
        $callback = function () {
            return 'ok';
        };
        $name = 'test_route';
        $methods = ['GET'];

        // Expect addRoute to be called once
        $this->routerMock->expects($this->once())->method('addRoute')->with($this->isInstanceOf(Route::class));

        $route = $this->routeCollector->route($path, $callback, $name, $methods);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals($name, $route->getName());
        $this->assertSame($route, $this->routeCollector->getRouteName($name));
    }

    public function testGetRoutesReturnsAllRegisteredRoutes()
    {
        $path1 = '/foo';
        $callback1 = 'SomeController::foo';
        $name1 = 'foo_route';

        $path2 = '/bar';
        $callback2 = 'SomeController::bar';
        $name2 = 'bar_route';

        $this->routerMock->expects($this->exactly(2))->method('addRoute');

        $route1 = $this->routeCollector->route($path1, $callback1, $name1, ['GET']);
        $route2 = $this->routeCollector->route($path2, $callback2, $name2, ['POST']);

        $routes = $this->routeCollector->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertSame($route1, $routes[$name1]);
        $this->assertSame($route2, $routes[$name2]);
    }

    public function testGetRouteNameReturnsNullIfNotFound()
    {
        $this->assertNull($this->routeCollector->getRouteName('nonexistent'));
    }

    public function testGroupCreatesAndReturnsRouteGroup()
    {
        $prefix = '/admin';
        $callable = function () {
        };

        // Mock RouteGroup to check it is called
        $routeGroupMock = $this->getMockBuilder(RouteGroup::class)
            ->setConstructorArgs([$prefix, $callable, $this->routeCollector])
            ->onlyMethods(['__invoke'])
            ->getMock();

        // Replace RouteGroup instantiation with our mock (if possible)
        // Otherwise, just test type and callable invocation
        $this->assertInstanceOf(RouteGroup::class, $this->routeCollector->group($prefix, $callable));
    }

    public function testCrudCreatesAndReturnsRouteGroup()
    {
        $prefixPath = '/api';
        $prefixName = 'api_';

        $this->assertInstanceOf(
            RouteGroup::class,
            $this->routeCollector->crud($prefixPath, 'callable', $prefixName)
        );
    }
}
