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
#[Bench\AfterClassMethods(['delTree'])]
#[Bench\ParamProviders(['dispatchers'])]
final class RealLifeExampleBench
{
    /** @var array<string, Router> */
    private array $router;

    /**
     * @throws CacheException
     */
    public function registerDispatchers(): void
    {
        $this->router = [
            'mark_cache_disabled' => DispatcherForBenchmark::realLifeExample('mark'),
            'mark_cache_enabled' => DispatcherForBenchmark::realLifeExample('mark', true),
            'named_cache_disabled' => DispatcherForBenchmark::realLifeExample('named'),
            'named_cache_enabled' => DispatcherForBenchmark::realLifeExample('named', true),
        ];
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'firstRoute'])]
    public function staticFirstRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'firstRoute'])]
    public function dynamicFirstRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/page/hello-word'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'lastRoute'])]
    public function staticLastRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/admin/category'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'lastRoute'])]
    public function dynamicLastRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/admin/category/123'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'invalidMethod'])]
    public function staticInvalidMethod(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('PUT', '/about-us'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'invalidMethod'])]
    public function dynamicInvalidMethod(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('PATCH', '/shop/category/123'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['unknownRoute'])]
    public function unknownRoute(array $params): void
    {
        $this->router[$params['router']]->match(new ServerRequest('GET', '/shop/product/awesome'));
    }

    /** @param array{router: string} $params
     * @throws InvalidArgumentException
     */
    #[Bench\Subject]
    #[Bench\Groups(['longestRoute'])]
    public function longestRoute(array $params): void
    {
        $this->router[$params['router']]->match(
            new ServerRequest(
                'GET',
                '/shop/category/123/product/search/status:sale'
            )
        );
    }

    public static function delTree(): void
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
