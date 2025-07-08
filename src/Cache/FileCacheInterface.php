<?php

namespace Pg\Router\Cache;

/**
 * Interface for file-based cache
 */
interface FileCacheInterface
{
    /**
     * Initialize the cache
     */
    public function init(): void;

    /**
     * Get cache data by key
     *
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Set cache data by key
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Clear all cache data
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Check if a cache exists for the key
     *
     * @param string $key Cache key
     * @return bool True if a cache exists
     */
    public function has(string $key): bool;

    /**
     * Delete cache data by key
     *
     * @param string $key Cache key
     * @return void
     */
    public function delete(string $key): void;

    /**
     * Fetch data from a cache or compute it if not cached
     *
     * @param string $key Cache key
     * @param callable $dataFetcher Data fetcher function
     * @return mixed Cached data or result of data fetcher
     */
    public function fetch(string $key, callable $dataFetcher): mixed;
}
