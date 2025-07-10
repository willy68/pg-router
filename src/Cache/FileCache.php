<?php

namespace Pg\Router\Cache;

use Closure;
use RuntimeException;

class FileCache implements FileCacheInterface
{
    private static array|string|Closure $error_handler;

    private string $cacheDir;
    private bool $useCache;

    public function __construct(
        ?string $cacheDir = null,
        bool $useCache = false
    ) {
        self::$error_handler = static function (): void {
        };
        $this->cacheDir = $cacheDir ?? 'tmp' . DIRECTORY_SEPARATOR . 'pg-router-cache';
        $this->useCache = $useCache;
        $this->init();
    }

    public function init(): void
    {
        if (!$this->useCache || is_dir($this->cacheDir)) {
            return;
        }

        set_error_handler(self::$error_handler);
        $created = mkdir($this->cacheDir, 0755, true);
        restore_error_handler();

        if ($created === false) {
            throw new RuntimeException('Impossible to create Cache directory: ' . $this->cacheDir);
        }
    }

    public function clear(): void
    {
        if (!$this->useCache) {
            return;
        }

        $files = glob($this->cacheDir . '/*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function has(string $key): bool
    {
        if (!$this->useCache) {
            return false;
        }

        $cachePath = $this->getCachePath($key);
        return file_exists($cachePath);
    }

    private function getCachePath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.php';
    }

    public function delete(string $key): void
    {
        if (!$this->useCache) {
            return;
        }

        $cachePath = $this->getCachePath($key);
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
    }

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

    public function get(string $key): mixed
    {
        if (!$this->useCache) {
            return null;
        }

        $cachePath = $this->getCachePath($key);

        set_error_handler(self::$error_handler);
        $result = include $cachePath;
        restore_error_handler();

        if ($result === false) {
            return null;
        }

        return $result;
    }

    public function set(string $key, mixed $value): int|bool
    {
        if (!$this->useCache) {
            return false;
        }

        $cachePath = $this->getCachePath($key);

        if (file_exists($cachePath) && !is_writable($cachePath)) {
            throw new RuntimeException('Cache file is not writable: ' . $cachePath);
        }

        return file_put_contents(
            $cachePath,
            '<?php return ' . var_export($value, true) . ';',
            LOCK_EX
        );
    }
}
