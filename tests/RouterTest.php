<?php

namespace PgTest\Router;

use Exception;
use FilesystemIterator;
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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class RouterTest extends TestCase
{
    public function delTree(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileInfo->getRealPath());
        }

        rmdir($dir);
        return true;
    }

    public function testAddAndGetRoute(): void
    {
        $router = new Router();
        $route = $router->route('/test', 'TestController::index', 'test.route', ['GET']);

        $this->assertSame($route, $router->getRouteName('test.route'));
    }

    public function testAddAndGetTokens(): void
    {
        $tokens = ['id' => '[0-9]+', 'slug' => '[a-zA-Z-]+[a-zA-Z0-9_-]+'];
        $router = new Router();
        $router->setTokens($tokens);
        $route = $router->route('/test', 'TestController::index', 'test.route', ['GET']);

        $this->assertSame($tokens, $router->getTokens());
        $this->assertSame($tokens, $route->getTokens());
        $this->assertSame($route, $router->getRouteName('test.route'));
    }

    public function testPriorityTokens(): void
    {
        $tokens = ['id' => '[0-9]+', 'slug' => '[a-zA-Z-]+[a-zA-Z0-9_-]+'];
        $router = new Router();
        $router->setTokens($tokens);

        $this->assertSame($tokens, $router->getTokens());

        $router->setTokens(['id' => '\d+']);
        $this->assertSame(['id' => '\d+', 'slug' => '[a-zA-Z-]+[a-zA-Z0-9_-]+'], $router->getTokens());
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

        $uri = $router->generateUri('test.uri', ['id' => 42], ['bar' => 'baz']);
        $this->assertSame('/test/42?bar=baz', $uri);

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

        // test with a bad attribute
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
            Router::CONFIG_CACHE_DIR => '/tmp/cache_dir'
        ]);

        $reflection = new ReflectionClass($router);
        $property = $reflection->getProperty('cachePool');
        $property->setValue($router, $cachePool);

        $data = $this->invokeLoadParsedData($router);

        $this->assertSame($cachedData, $data);
        $this->delTree('/tmp/cache_dir');
    }

    /**
     * @throws ReflectionException
     */
    private function invokeLoadParsedData(Router $router): array
    {
        $reflection = new ReflectionClass($router);
        $method = $reflection->getMethod('loadParsedData');
        return $method->invoke($router);
    }

    /**
     * @throws ReflectionException
     */
    private function invokeParsedData(Router $router): array
    {
        $reflection = new ReflectionClass($router);
        $method = $reflection->getMethod('getParsedData');
        return $method->invoke($router);
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
        $regexCollector->expects($this->once())->method('addRoutes');
        $regexCollector->method('getData')->willReturn(['parsed' => 'data']);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->expects($this->once())->method('set')->with(['parsed' => 'data']);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);
        $cachePool->expects($this->once())->method('save')->with($cacheItem);

        $router = new Router($regexCollector, null, [
            Router::CONFIG_CACHE_ENABLED => true,
            Router::CONFIG_CACHE_DIR => '/tmp/cache_dir'
        ]);
        $router->addRoute($route);

        $reflection = new ReflectionClass($router);
        $property = $reflection->getProperty('cachePool');
        $property->setValue($router, $cachePool);

        $this->invokeParsedData($router);

        // Clean up
        $this->delTree('/tmp/cache_dir');
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

        $router = new Router(
            null,
            null,
            [
                Router::CONFIG_CACHE_ENABLED => true,
                Router::CONFIG_CACHE_DIR => $tmpDir,
            ]
        );

        $router->route('/cache-test/{id:\d+}', 'TestController::cache', 'cache.route', ['GET']);

        // First match to populate the cache
        $request = new ServerRequest('GET', '/cache-test/123');
        $result = $router->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('cache.route', $result->getMatchedRouteName());
        $this->assertSame(['id' => '123'], $result->getMatchedAttributes());

        // Simulate a new Router instance with the same cache config
        $router2 = new Router(
            null,
            null,
            [
                Router::CONFIG_CACHE_ENABLED => true,
                Router::CONFIG_CACHE_DIR => $tmpDir,
            ]
        );
        // Add the same route (simulate fresh boot, but cache should be hit)
        $router2->route('/cache-test/{id:\d+}', 'TestController::cache', 'cache.route', ['GET']);

        $request2 = new ServerRequest('GET', '/cache-test/456');
        $result2 = $router2->match($request2);

        $this->assertTrue($result2->isSuccess());
        $this->assertSame('cache.route', $result2->getMatchedRouteName());
        $this->assertSame(['id' => '456'], $result2->getMatchedAttributes());

        // Clean up
        $this->delTree($tmpDir);
    }

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function testMatchRouteWithCache(): void
    {
        $cache = new ArrayAdapter();

        $router = new Router(
            null,
            null,
            [
                Router::CONFIG_CACHE_ENABLED => true,
                Router::CONFIG_CACHE_POOL_FACTORY => fn(): CacheItemPoolInterface => $cache,
            ]
        );

        $router->route('/about', fn() => 'about', 'about', ['GET']);

        // First access: forces generation and caching
        $request = new ServerRequest('GET', '/about');
        $result = $router->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('about', $result->getMatchedRoute()->getName());

        // Simulates a new router instance => cache should be used
        $router2 = new Router(
            null,
            null,
            [
                Router::CONFIG_CACHE_ENABLED => true,
                Router::CONFIG_CACHE_POOL_FACTORY => fn(): CacheItemPoolInterface => $cache,
            ]
        );

        // Important: we need to add the same route for the name to be recognized
        $router2->route('/about', fn() => 'about', 'about', ['GET']);

        $request2 = new ServerRequest('GET', '/about');
        $result2 = $router2->match($request2);

        $this->assertTrue($result2->isSuccess());
        $this->assertSame('about', $result2->getMatchedRoute()->getName());
    }

    /**
     * @throws CacheException
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testClearCacheEmptiesCacheAndResetsParsedData(): void
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->once())->method('clear');

        $router = new Router(null, null, [
            Router::CONFIG_CACHE_ENABLED => true,
            Router::CONFIG_CACHE_DIR => '/tmp/cache_dir'
        ]);

        // Injection of the mocked cache
        $reflection = new ReflectionClass($router);
        $property = $reflection->getProperty('cachePool');
        $property->setValue($router, $cachePool);

        // Simulate cached data
        $reflection->getProperty('parsedData')->setValue($router, ['foo' => 'bar']);
        $reflection->getProperty('hasParsedData')->setValue($router, true);

        $router->clearCache();

        $this->assertNull($reflection->getProperty('parsedData')->getValue($router));
        $this->assertFalse($reflection->getProperty('hasParsedData')->getValue($router));
    }
}
