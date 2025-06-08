<?php

declare(strict_types=1);

namespace Benchmarks;

use PhpBench\Attributes as Bench;
use Pg\Router\Matcher\MarkDataMatcher;
use Pg\Router\Matcher\MatcherInterface;
use Pg\Router\Matcher\NamedMatcher;

#[Bench\Iterations(5)]
#[Bench\Revs(250)]
#[Bench\Warmup(3)]
#[Bench\BeforeMethods(['registerMatchers'])]
#[Bench\ParamProviders(['matchers'])]
final class RoutingWithRealLifeExampleBench
{
    /** @var array<string, MatcherInterface> */
    private array $router;

    /**
     */
    public function registerMatchers(): void
    {
        $this->router = [
            'mark_matcher' => MatcherForBenchmark::realLifeExample(MarkDataMatcher::class),
            'named_matcher' => MatcherForBenchmark::realLifeExample(NamedMatcher::class),
        ];
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'firstRoute'])]
    public function staticFirstRoute(array $params): void
    {
        $this->router[$params['router']]->match('/', 'GET');
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'firstRoute'])]
    public function dynamicFirstRoute(array $params): void
    {
        $this->router[$params['router']]->match('/page/hello-word', 'GET');
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'lastRoute'])]
    public function staticLastRoute(array $params): void
    {
        $this->router[$params['router']]->match('/admin/category', 'GET');
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'lastRoute'])]
    public function dynamicLastRoute(array $params): void
    {
        $this->router[$params['router']]->match('/admin/category/123', 'GET');
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['static', 'invalidMethod'])]
    public function staticInvalidMethod(array $params): void
    {
        $this->router[$params['router']]->match('/about-us', 'PUT');
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['dynamic', 'invalidMethod'])]
    public function dynamicInvalidMethod(array $params): void
    {
        $this->router[$params['router']]->match('/shop/category/123', 'PATCH');
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['unknownRoute'])]
    public function unknownRoute(array $params): void
    {
        $this->router[$params['router']]->match('/shop/product/awesome', 'GET');
    }

    /** @param array{router: string} $params
     */
    #[Bench\Subject]
    #[Bench\Groups(['longestRoute'])]
    public function longestRoute(array $params): void
    {
        $this->router[$params['router']]->match('/shop/category/123/product/search/status:sale', 'GET');
    }

    /** @return iterable<string, array<string, mixed>> */
    public function matchers(): iterable
    {
        foreach (['mark_matcher', 'named_matcher'] as $router) {
            yield $router => ['router' => $router];
        }
    }
}
