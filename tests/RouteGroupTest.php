<?php

declare(strict_types=1);

namespace PgTest\Router;

use Pg\Router\RouteGroup;
use Pg\Router\RouteCollectionInterface;
use Pg\Router\RouterInterface;
use Pg\Router\Route;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class RouteGroupTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testConstructorSetsProperties()
    {
        /** @var RouteCollectionInterface $router */
        $router = $this->createMock(RouteCollectionInterface::class);
        $callback = function () {
        };
        $group = new RouteGroup('/prefix', $callback, $router);

        $this->assertSame('/prefix', $group->getPrefix());
    }

    /**
     * @throws Exception
     */
    public function testGetPrefix()
    {
        /** @var RouterInterface $router */
        $router = $this->createMock(RouterInterface::class);
        $group = new RouteGroup('/admin', function () {
        }, $router);
        $this->assertSame('/admin', $group->getPrefix());
    }

    /**
     * @throws Exception
     */
    public function testInvokeCallsCallback()
    {
        /** @var RouterInterface $router */
        $router = $this->createMock(RouterInterface::class);
        $called = false;
        $group = new RouteGroup('/admin', function ($arg) use (&$called) {
            $called = true;
            $this->assertInstanceOf(RouteGroup::class, $arg);
        }, $router);
        $group();
        $this->assertTrue($called);
    }

    /**
     * @throws Exception
     */
    public function testRouteAddsPrefixAndSetsParentGroup()
    {
        $router = $this->createMock(RouterInterface::class);
        $route = $this->createMock(Route::class);

        $router->expects($this->once())
            ->method('route')
            ->with('/admin/foo', 'cb', 'name', ['GET'])
            ->willReturn($route);

        $route->expects($this->once())
            ->method('setParentGroup')
            ->with($this->isInstanceOf(RouteGroup::class));

        /** @var RouterInterface $router */
        $group = new RouteGroup('/admin', function () {
        }, $router);
        $result = $group->route('/foo', 'cb', 'name', ['GET']);
        $this->assertSame($route, $result);
    }

    /**
     * @throws Exception
     */
    public function testRouteWithSlashPath()
    {
        $router = $this->createMock(RouterInterface::class);
        $route = $this->createMock(Route::class);

        $router->expects($this->once())
            ->method('route')
            ->with('/prefix', 'cb', null, null)
            ->willReturn($route);

        $route->expects($this->once())
            ->method('setParentGroup')
            ->with($this->isInstanceOf(RouteGroup::class));

        /** @var RouterInterface $router */
        $group = new RouteGroup('/prefix', function () {
        }, $router);
        $result = $group->route('/', 'cb');
        $this->assertSame($route, $result);
    }

    /**
     * @throws Exception
     */
    public function testCrudCallsVerbMethodsAndReturnsSelf()
    {
        $group = $this->getMockBuilder(RouteGroup::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'post', 'delete'])
            ->getMock();
        $route = $this->createMock(Route::class);

        $expected1 = ['/', 'UserController::index', 'user.index'];
        $expected2 = ['/new', 'UserController::create', 'user.create'];
        $expected3 = ['/new', 'UserController::edit', 'user.edit'];
        $matcher = $this->exactly(3);
        $group->expects($matcher)
            ->method('get')
            ->willReturnCallback(
                function (
                    string $path,
                    string $callback,
                    string $name
                ) use (
                    $matcher,
                    $expected1,
                    $expected2,
                    $expected3
                ) {
                    match ($matcher->numberOfInvocations()) {
                        1 => $this->assertSame($expected1, [$path, $callback, $name]),
                        2 => $this->assertSame($expected2, [$path, $callback, $name]),
                        3 => $this->assertSame($expected3, [$path, $callback, $name]),
                    };
                }
            )
            ->willReturn($route);

        $expected1 = ['/new', 'UserController::create', 'user.create.post'];
        $expected2 = ['/{id:\d+}', 'UserController::edit', 'user.edit.post'];
        $matcher = $this->exactly(2);
        $group->expects($matcher)
            ->method('post')
            ->willReturnCallback(
                function (string $path, string $callback, string $name) use ($matcher, $expected1, $expected2) {
                    match ($matcher->numberOfInvocations()) {
                        1 => $this->assertSame($expected1, [$path, $callback, $name]),
                        2 => $this->assertSame($expected2, [$path, $callback, $name]),
                    };
                }
            )
            ->willReturn($route);

        $group->expects($this->once())
            ->method('delete')
            ->with('/{id:\d+}', 'UserController::delete', 'user.delete')
            ->willReturn($route);

        /** @var RouteGroup $group */
        $result = $group->crud('UserController', 'user');
        $this->assertSame($group, $result);
    }

    /**
     * @throws Exception
     */
    public function testCrudAddsAllCrudRoutes()
    {
        $router = $this->createMock(RouterInterface::class);
        $route = $this->createMock(Route::class);

        $router->method('route')->willReturn($route);
        $route->method('setParentGroup')->willReturnSelf();

        $group = $this->getMockBuilder(RouteGroup::class)
            ->setConstructorArgs(['/users', function () {
            }, $router])
            ->onlyMethods(['get', 'post', 'delete'])
            ->getMock();

        $group->expects($this->exactly(3))->method('get');
        $group->expects($this->exactly(2))->method('post');
        $group->expects($this->once())->method('delete');

        /** @var RouteGroup $group */
        $result = $group->crud('UserController', 'user');
        $this->assertSame($group, $result);
    }
}
