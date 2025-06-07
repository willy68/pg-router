<?php

declare(strict_types=1);

namespace Benchmarks;

use PhpBench\Attributes as Bench;
use Psr\Cache\CacheException;

#[Bench\Iterations(5)]
#[Bench\Revs(100)]
#[Bench\Warmup(3)]
final class RouteRegisterBench
{
    /**
     * @throws CacheException
     */
    #[Bench\Subject]
    #[Bench\Groups(['mark_cache_disabled'])]
    public function markCacheDisabled(): void
    {
        DispatcherForBenchmark::realLifeExample('mark');
    }

    /**
     * @throws CacheException
     */
    #[Bench\Subject]
    #[Bench\Groups(['mark_cache_enabled'])]
    public function markCacheEnabled(): void
    {
        DispatcherForBenchmark::realLifeExample('mark', true);
    }
    /**
     * @throws CacheException
     */
    #[Bench\Subject]
    #[Bench\Groups(['named_cache_disabled'])]
    public function namedCacheDisabled(): void
    {
        DispatcherForBenchmark::realLifeExample('named');
    }

    /**
     * @throws CacheException
     */
    #[Bench\Subject]
    #[Bench\Groups(['named_cache_enabled'])]
    public function namedCacheEnabled(): void
    {
        DispatcherForBenchmark::realLifeExample('named', true);
    }
}
