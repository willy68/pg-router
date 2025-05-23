<?php

namespace PgTest\Router;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use Pg\Router\Exception\MissingAttributeException;
use Pg\Router\Exception\RouteNotFoundException;
use Pg\Router\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testAddAndGetRoute(): void
    {
        $router = new Router();
        $route = $router->route('/test', 'TestController::index', 'test.route', ['GET']);

        $this->assertSame($route, $router->getRouteName('test.route'));
    }

    /**
     * @throws Exception
     */
    public function testGenerateUri(): void
    {
        $router = new Router();
        $router->route('/test/{id:[0-9]+}', 'TestController::index', 'test.uri', ['GET']);

        $uri = $router->generateUri('test.uri', ['id' => 42]);
        $this->assertSame('/test/42', $uri);

        // test with bad route name
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage(
            sprintf('Route with name [%s] not found', 'test.uri.bad')
        );
        $router->generateUri('test.uri.bad', ['id' => 42]);

        // test without attributes
        $this->expectException(MissingAttributeException::class);
        $this->expectExceptionMessage(sprintf(
            'No replacement attributes found for this route [%s]',
            'test.uri'
        ));
        $router->generateUri('test.uri');

        // test with bad attribute
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Parameter value for [%s] did not match the regex `%s`',
            'id',
            '([0-9]+)'
        ));
        $router->generateUri('test.uri', ['id' => 'slug']);
    }

    public function testMatchSuccess(): void
    {
        $router = new Router();
        $router->route('/hello', 'HelloController::sayHello', 'hello', ['GET']);

        $request = new ServerRequest('GET', '/hello');
        $result = $router->match($request);

        $this->assertTrue($result->isSuccess());
    }

    public function testMatchFailure(): void
    {
        $router = new Router();
        $router->route('/hello', 'HelloController::sayHello', 'hello', ['GET']);

        $request = new ServerRequest('POST', '/hello');
        $result = $router->match($request);

        $this->assertFalse($result->isSuccess());
    }

    public function testCrudRoutes(): void
    {
        $router = new Router();
        $group = $router->crud('/users', 'UserController', 'user');

        $this->assertNotEmpty($router->getRoutes());
    }
}
