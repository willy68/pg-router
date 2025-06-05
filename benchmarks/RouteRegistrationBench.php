<?php

declare(strict_types=1);

namespace Benchmarks;

use PhpBench\Attributes as Bench;
use Psr\Cache\CacheException;

#[Bench\Iterations(5)]
#[Bench\Revs(100)]
#[Bench\Warmup(3)]
final class RouteRegistrationBench
{
    /**
     * @throws CacheException
     */
    #[Bench\Subject]
    #[Bench\Groups(['cache_disabled'])]
    public function cacheDisabled(): void
    {
        DispatcherForBenchmark::realLifeExample();
    }

    /**
     * @throws CacheException
     */
    #[Bench\Subject]
    #[Bench\Groups(['cache_enabled'])]
    public function cacheEnabled(): void
    {
        DispatcherForBenchmark::realLifeExample(true);
    }
}
