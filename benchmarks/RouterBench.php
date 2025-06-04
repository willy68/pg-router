<?php

namespace Benchmarks;

use Exception;
use FilesystemIterator;
use Pg\Router\Router;
use GuzzleHttp\Psr7\ServerRequest;
use PhpBench\Attributes as Bench;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

#[Bench\Revs(100)]
#[Bench\Iterations(5)]
#[Bench\OutputTimeUnit('microseconds')]
#[Bench\OutputMode('throughput')]
class RouterBench
{
    private Router $router;
    private string $cacheDir = 'tmp/cache/router_bench';

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

    /**
     * Basic benchmark: add a single route.
     */
    #[Bench\Subject]
    public function benchAddRoute(): void
    {
        $router = new Router();
        $router->route('/hello', fn () => 'hi', 'hello_route');
    }

    /**
     * Basic match on static route.
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    public function benchMatchStaticRoute(): void
    {
        $router = new Router();
        $router->route('/static', fn () => 'ok', 'static', ['GET']);
        $request = new ServerRequest('GET', '/static');
        $router->match($request);
    }

    /**
     * Basic match on dynamic route.
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    public function benchMatchDynamicRoute(): void
    {
        $router = new Router();
        $router->route('/user/{id}', fn () => 'user', 'user_show', ['GET']);
        $request = new ServerRequest('GET', '/user/42');
        $router->match($request);
    }

    /**
     * URI generation benchmark.
     * @throws Exception
     */
    #[Bench\Subject]
    public function benchGenerateUri(): void
    {
        $router = new Router();
        $router->route('/post/{slug}', fn () => 'show', 'post_show');
        $router->generateUri('post_show', ['slug' => 'hello-world']);
    }

    /**
     * Benchmark route grouping with CRUD.
     */
    #[Bench\Subject]
    public function benchCrudRoutes(): void
    {
        $router = new Router();
        $router->crud('/articles', 'ArticleController', 'article');
    }

    /**
     * Match route with warm cache.
     * @throws InvalidArgumentException
     * @throws CacheException
     */
    #[Bench\Subject]
    public function benchMatchWithCache(): void
    {
        $this->setupRouterWithCache(50);
        $request = new ServerRequest('GET', '/route10');
        $this->router->match($request);
    }

    /**
     * Match route with cold (no) cache.
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    public function benchMatchWithoutCache(): void
    {
        $router = new Router();
        foreach (range(1, 50) as $i) {
            $router->route("/route$i", fn () => null, "route_$i");
        }
        $request = new ServerRequest('GET', '/route10');
        $router->match($request);
    }

    /**
     * Match route while scaling number of total routes.
     *
     * @param array{count: int, target: int} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\ParamProviders(['provideRouteCounts'])]
    public function benchMatchDynamicVolume(array $params): void
    {
        $router = new Router();
        foreach (range(1, $params['count']) as $i) {
            $router->route("/route$i", fn () => null, "route_$i");
        }
        $request = new ServerRequest('GET', "/route{$params['target']}");
        $router->match($request);
    }

    /**
     * Provide route volume test sizes.
     */
    public function provideRouteCounts(): array
    {
        return [
            '10_routes' => ['count' => 10, 'target' => 5],
            '100_routes' => ['count' => 100, 'target' => 50],
            //'1000_routes' => ['count' => 1000, 'target' => 999],
        ];
    }

    /**
     * Prepares router with cache enabled and 50 routes.
     * @throws CacheException
     * @throws Exception
     */
    private function setupRouterWithCache(int $routeCount): void
    {
        // Clean cache
        /*if (is_dir($this->cacheDir)) {
            $this->delTree($this->cacheDir);
        }*/

        $this->router = new Router(
            null,
            null,
            [
                Router::CONFIG_CACHE_ENABLED => true,
                Router::CONFIG_CACHE_DIR => $this->cacheDir,
                Router::CONFIG_CACHE_POOL_FACTORY => fn() =>
                new PhpFilesAdapter('RouterBench', 0, $this->cacheDir)
            ]
        );

        foreach (range(1, $routeCount) as $i) {
            $this->router->route("/route$i", fn () => null, "route_$i");
        }

        // Prime the cache
        $request = new ServerRequest('GET', '/route1');
        $this->router->match($request);
    }
}
