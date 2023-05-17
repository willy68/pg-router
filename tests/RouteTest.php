<?php

namespace PgTest\Router;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Pg\Router\Exception\ImmutableProperty;
use Pg\Router\Exception\InvalidArgumentException;
use Pg\Router\Route;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    protected string $callback;

    protected function setUp(): void
    {
        parent::setUp();
        $this->callback = 'MonController::method';
    }

    public function testRouteGetPath(): void
    {
        $route = new Route('/foo', $this->callback);

        self::assertSame('/foo', $route->getPath());
    }

    public function testRouteGetName(): void
    {
        $route = new Route('/foo', $this->callback, 'test');

        self::assertSame('test', $route->getName());
    }

    public function testImmutableName()
    {
        $route = new Route('/foo', $this->callback, 'test');
        $this->expectException(ImmutableProperty::class);
        $this->expectExceptionMessage(Route::class . ' ::$name is immutable once set');
        $route->setName('/bar');
    }

    public function testRouteWithNullNameMatchPathMethodGet(): void
    {
        $route = new Route('/foo', $this->callback, null, [RequestMethod::METHOD_GET]);

        self::assertSame('/foo^' . RequestMethod::METHOD_GET, $route->getName());
    }

    public function testRouteWithNullNameMatchPathMethodGetAndPost(): void
    {
        $route = new Route(
            '/foo',
            $this->callback,
            null,
            [RequestMethod::METHOD_GET, RequestMethod::METHOD_POST]
        );

        self::assertSame(
            '/foo^' . RequestMethod::METHOD_GET . Route::HTTP_METHOD_SEPARATOR . RequestMethod::METHOD_POST,
            $route->getName()
        );
    }

    public function testRouteGetCallback(): void
    {
        $route = new Route('/foo', $this->callback);

        self::assertSame($this->callback, $route->getCallback());
    }

    public function testRouteAnyMethodsByDefault(): void
    {
        $route = new Route('/foo', $this->callback);

        self::assertSame(Route::HTTP_METHOD_ANY, $route->getAllowedMethods());
    }

    public function testRouteGetAllowedMethods(): void
    {
        $methods = [RequestMethod::METHOD_GET, RequestMethod::METHOD_POST];
        $route   = new Route('/foo', $this->callback, 'test', $methods);

        self::assertSame($methods, $route->getAllowedMethods());
    }

    public function testRouteCanMatchMethod(): void
    {
        $methods = [RequestMethod::METHOD_GET, RequestMethod::METHOD_POST];
        $route   = new Route('/foo', $this->callback, 'test', $methods);

        self::assertTrue($route->allowsMethod(RequestMethod::METHOD_GET));
        self::assertTrue($route->allowsMethod(RequestMethod::METHOD_POST));
        self::assertFalse($route->allowsMethod(RequestMethod::METHOD_PATCH));
        self::assertFalse($route->allowsMethod(RequestMethod::METHOD_DELETE));
    }

    public function testConstructorShouldRaiseExceptionIfMethodsArgumentIsAnEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Http methods array is empty');

        new Route('/foo', $this->callback, 'test', []);
    }
}
