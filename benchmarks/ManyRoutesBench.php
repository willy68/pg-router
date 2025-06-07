<?php

declare(strict_types=1);

namespace Benchmarks;

use GuzzleHttp\Psr7\ServerRequest;
use Pg\Router\Router;
use PhpBench\Attributes as Bench;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

#[Bench\Iterations(5)]
#[Bench\Revs(250)]
#[Bench\Warmup(3)]
#[Bench\BeforeMethods(['registerDispatchers'])]
#[Bench\AfterMethods('delTree')]
#[Bench\ParamProviders(['dispatchers'])]
final class ManyRoutesBench
{
    /** @var array<string, Router> */
    private array $router;

    /**
     * @throws CacheException
     */
    public function registerDispatchers(): void
    {
        $this->router = [
            'mark_cache_disabled' => DispatcherForBenchmark::manyRoutes('mark', false),
            'mark_cache_enabled' => DispatcherForBenchmark::manyRoutes('mark', true),
            'named_cache_disabled' => DispatcherForBenchmark::manyRoutes('named', false),
            'named_cache_enabled' => DispatcherForBenchmark::manyRoutes('named', true),
        ];
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'firstRoute'])]
    public function staticFirstRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/abc0'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'firstRoute'])]
    public function dynamicFirstRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/abcbar/0'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'lastRoute'])]
    public function staticLastRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/abc399'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'lastRoute'])]
    public function dynamicLastRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/abcbar/399'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'invalidMethod'])]
    public function staticInvalidMethod(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('PUT', '/abc399'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'invalidMethod'])]
    public function dynamicInvalidMethod(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('PUT', '/abcbar/399'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['unknownRoute'])]
    public function unknownRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/testing'));
    }

    public function delTree(): void
    {
        DispatcherForBenchmark::delTree(DispatcherForBenchmark::CACHE_DIR);
    }

    /** @return iterable<string, array<string, mixed>> */
    public function dispatchers(): iterable
    {
        foreach (
            ['mark_cache_disabled', 'mark_cache_enabled', 'named_cache_disabled', 'named_cache_enabled'] as $router
        ) {
            yield $router => ['router' => $router];
        }
    }
}
