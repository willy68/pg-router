<?php

declare(strict_types=1);

namespace Pg\Router\Cache;

use Pg\Router\Cache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use DateInterval;
use DateTime;

class Psr16FileCache implements CacheInterface, FileCacheInterface
{
    private FileCache $fileCache;
    private string $namespace;

    public function __construct(
        ?string $cacheDir = null,
        bool $useCache = true,
        string $namespace = ''
    ) {
        $this->fileCache = new FileCache($cacheDir, $useCache);
        $this->namespace = $namespace;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return $this->fileCache->clear();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        $value = $this->fileCache->get($this->getNamespacedKey($key));

        if ($value === null) {
            return $default;
        }

        if (!is_array($value) || !isset($value['data'])) {
            return $default;
        }

        if ($value['expires'] !== null && $value['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $value['data'];
    }

    /**
     * Validate a cache key according to PSR-16.
     *
     * @param string $key
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Empty cache key.');
        }

        if (preg_match('/[\{\}\(\)\/\\@:]/', $key)) {
            throw new InvalidArgumentException(
                sprintf('Invalid character in cache key: %s', $key)
            );
        }
    }

    /**
     * Get a namespaced cache key.
     */
    private function getNamespacedKey(string $key): string
    {
        return $this->namespace !== '' ? "{$this->namespace}_$key" : $key;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        return $this->fileCache->delete($this->getNamespacedKey($key));
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $result = true;
        foreach ($values as $key => $value) {
            $result = $this->set($key, $value, $ttl) && $result;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);

        $expires = null;
        if ($ttl !== null) {
            if ($ttl instanceof DateInterval) {
                $expires = (new DateTime())->add($ttl)->getTimestamp();
            } else {
                $expires = time() + $ttl;
            }
        }

        return (bool)$this->fileCache->set(
            $this->getNamespacedKey($key),
            [
                'data' => $value,
                'expires' => $expires,
                'created' => time()
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $result = true;
        foreach ($keys as $key) {
            $result = $this->delete($key) && $result;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        return $this->get($key, $this) !== $this;
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function fetch(string $key, callable $dataFetcher): mixed
    {
        $cachedData = $this->get($key);

        if ($cachedData !== null) {
            return $cachedData;
        }

        $result = $dataFetcher();
        $this->set($key, $result);

        return $result;
    }
}
