<?php

namespace PgTest\Router;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Pg\Router\Exception\ImmutableProperty;
use Pg\Router\Exception\InvalidArgumentException;
use Pg\Router\Route;
use Pg\Router\RouteCollector;
use Pg\Router\RouteGroup;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    protected string $callback;

    /**
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->callback = 'MonController::method';
    }

    /**
     * @throws Exception
     */
    protected function getCollector(): MockObject|RouteCollector
    {
        return $this->createMock(RouteCollector::class);
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
        self::expectException(ImmutableProperty::class);
        self::expectExceptionMessage(Route::class . ' ::$name is immutable once set');
        $route->setName('/bar');
    }

    public function testAddAndGetTokens(): void
    {
        $tokens = ['id' => '[0-9]+', 'slug' => '[a-zA-Z-]+[a-zA-Z0-9_-]+'];
        $route = new Route('/test', 'TestController::index', 'test.route', ['GET']);
        $route->setTokens($tokens);

        $this->assertSame($tokens, $route->getTokens());
    }

    public function testPriorityTokens(): void
    {
        $tokens = ['id' => '[0-9]+', 'slug' => '[a-zA-Z-]+[a-zA-Z0-9_-]+'];
        $route = new Route('/test', 'TestController::index', 'test.route', ['GET']);
        $route->setTokens($tokens);

        $this->assertSame($tokens, $route->getTokens());

        $route->setTokens(['id' => '\d+']);
        $this->assertSame($tokens, $route->getTokens());
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
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Http methods array is empty');

        new Route('/foo', $this->callback, 'test', []);
    }

    /**
     * @throws Exception
     */
    public function testRouteGetRouteGroup()
    {
        $route = new Route('/prefix/foo', $this->callback);

        $routeGroup = new RouteGroup(
            '/prefix',
            fn(RouteGroup $routeGroup) => $routeGroup,
            $this->getCollector()
        );

        $route->setParentGroup($routeGroup);
        self::assertSame($routeGroup, $route->getParentGroup());
    }

    /**
     * @throws Exception
     */
    public function testRouteWithRouteGroupPrefix()
    {
        $collector = $this->getCollector();
        $collector->expects($this->once())
            ->method('route')
            ->willReturn(new Route('/prefix/foo', $this->callback));

        $routeGroup = new RouteGroup(
            '/prefix',
            fn(RouteGroup $routeGroup) => $routeGroup,
            $collector
        );

        $route = $routeGroup->route('/foo', $this->callback, 'test');
        self::assertSame('/prefix/foo', $route->getPath());
    }

    public function testRouteGetHostNullByDefault()
    {
        $route = new Route('/foo', $this->callback, 'test');

        self::assertNull($route->getHost());
    }

    public function testRouteGetHost()
    {
        $route = new Route('/foo', $this->callback, 'test');

        $route->setHost('my-domain.com');
        self::assertSame('my-domain.com', $route->getHost());
    }

    public function testRouteGetPortNullByDefault()
    {
        $route = new Route('/foo', $this->callback, 'test');

        self::assertNull($route->getPort());
    }

    public function testRouteGetPort()
    {
        $route = new Route('/foo', $this->callback, 'test');

        $route->setPort(8000);
        self::assertSame(8000, $route->getPort());
    }

    public function testRouteAnySchemesByDefault(): void
    {
        $route = new Route('/foo', $this->callback);

        self::assertTrue($route->allowsAnyScheme());
        self::assertSame(Route::HTTP_SCHEME_ANY, $route->getSchemes());
    }

    public function testRouteGetAllowedSchemes(): void
    {
        $schemes = ['http', 'https'];
        $route   = new Route('/foo', $this->callback, 'test');

        $route->setSchemes($schemes);
        self::assertSame($schemes, $route->getSchemes());
    }

    public function testRouteCanMatchScheme(): void
    {
        $schemes = ['http', 'https'];
        $route   = new Route('/foo', $this->callback, 'test');

        $route->setSchemes($schemes);
        self::assertTrue($route->allowsScheme('http'));
        self::assertTrue($route->allowsScheme('https'));
        self::assertFalse($route->allowsAnyScheme());
    }
}
