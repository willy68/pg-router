<?php

namespace PgTest\Router;

use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use Pg\Router\Exception\MissingAttributeException;
use Pg\Router\Exception\RouteNotFoundException;
use Pg\Router\RegexCollector\RegexCollectorInterface;
use Pg\Router\Route;
use Pg\Router\Router;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

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
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Parameter value for [%s] did not match the regex `%s`',
            'id',
            '([0-9]+)'
        ));
        $router->generateUri('test.uri', ['id' => 'slug']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testMatchSuccess(): void
    {
        $router = new Router();
        $router->route('/hello-{name:\w+}', 'HelloController::sayHello', 'hello', ['GET']);

        $request = new ServerRequest('GET', '/hello-john');
        $result = $router->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('hello', $result->getMatchedRouteName());
        $this->assertSame(['name' => 'john'], $result->getMatchedAttributes());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testMatchFailureMethodNotAllowed(): void
    {
        $router = new Router();
        $router->route('/hello', 'HelloController::sayHello', 'hello', ['GET']);

        $request = new ServerRequest('POST', '/hello');
        $result = $router->match($request);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['GET'], $result->getAllowedMethods());
    }

    public function testCrudRoutes(): void
    {
        $router = new Router();
        $router->crud('/users', 'UserController', 'user');

        $this->assertNotEmpty($router->getRoutes());
    }

    /**
     * @throws CacheException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws ReflectionException
     */
    public function testCacheIsUsedWhenEnabledAndHit(): void
    {
        $cachedData = ['some' => 'cached_data'];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($cachedData);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);

        $router = new Router(null, null, [
            Router::CONFIG_CACHE_ENABLED => true,
            Router::CONFIG_CACHE_FILE => '/tmp/cache_file.php'
        ]);

        $reflection = new ReflectionClass($router);
        $property = $reflection->getProperty('cachePool');
        $property->setAccessible(true);
        $property->setValue($router, $cachePool);

        $data = $this->invokeParsedData($router);

        $this->assertSame($cachedData, $data);
    }

    /**
     * @throws CacheException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws ReflectionException
     */
    public function testCacheIsSavedWhenMiss(): void
    {
        $route = new Route('/test', 'callback', 'test');

        $regexCollector = $this->createMock(RegexCollectorInterface::class);
        $regexCollector->expects($this->once())->method('addRoute');
        $regexCollector->method('getData')->willReturn(['parsed' => 'data']);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->expects($this->once())->method('set')->with(['parsed' => 'data']);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);
        $cachePool->expects($this->once())->method('save')->with($cacheItem);

        $router = new Router($regexCollector, null, [
            Router::CONFIG_CACHE_ENABLED => true,
            Router::CONFIG_CACHE_FILE => '/tmp/cache_file.php'
        ]);
        $router->addRoute($route);

        $reflection = new ReflectionClass($router);
        $property = $reflection->getProperty('cachePool');
        $property->setAccessible(true);
        $property->setValue($router, $cachePool);

        $this->invokeParsedData($router);
    }

    /**
     * @throws CacheException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws ReflectionException
     */
    public function testNoCacheWhenDisabled(): void
    {
        $regexCollector = $this->createMock(RegexCollectorInterface::class);
        $regexCollector->expects($this->once())->method('getData')->willReturn(['fresh' => 'data']);

        $router = new Router($regexCollector, null, [
            Router::CONFIG_CACHE_ENABLED => false
        ]);

        $route = new Route('/test', 'callback', 'test');
        $router->addRoute($route);

        $data = $this->invokeParsedData($router);

        $this->assertEquals(['fresh' => 'data'], $data);
    }

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function testCacheStoresAndRestoresParsedData(): void
    {
        $tmpDir = sys_get_temp_dir() . '/router_cache_' . uniqid();
        mkdir($tmpDir);
        $cacheFile = $tmpDir . '/router_cache.php';

        $router = new Router(
            null,
            null,
            [
            Router::CONFIG_CACHE_ENABLED => true,
            Router::CONFIG_CACHE_FILE => $cacheFile,
            ]
        );

        $router->route('/cache-test/{id:\d+}', 'TestController::cache', 'cache.route', ['GET']);

    // First match to populate cache
        $request = new \GuzzleHttp\Psr7\ServerRequest('GET', '/cache-test/123');
        $result = $router->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('cache.route', $result->getMatchedRouteName());
        $this->assertSame(['id' => '123'], $result->getMatchedAttributes());

    // Simulate a new Router instance with same cache config
        $router2 = new Router(
            null,
            null,
            [
            Router::CONFIG_CACHE_ENABLED => true,
            Router::CONFIG_CACHE_FILE => $cacheFile,
            ]
        );
    // Add the same route (simulate fresh boot, but cache should be hit)
        $router2->route('/cache-test/{id:\d+}', 'TestController::cache', 'cache.route', ['GET']);

        $request2 = new \GuzzleHttp\Psr7\ServerRequest('GET', '/cache-test/456');
        $result2 = $router2->match($request2);

        $this->assertTrue($result2->isSuccess());
        $this->assertSame('cache.route', $result2->getMatchedRouteName());
        $this->assertSame(['id' => '456'], $result2->getMatchedAttributes());

    // Clean up
        array_map('unlink', glob($tmpDir . '/*'));
        rmdir($tmpDir);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeParsedData(Router $router): array
    {
        $reflection = new ReflectionClass($router);
        $method = $reflection->getMethod('getParsedData');
        $method->setAccessible(true);
        return $method->invoke($router);
    }
}
