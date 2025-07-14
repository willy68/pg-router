<?php

declare(strict_types=1);

namespace PgTest\Router\Cache;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Pg\Router\Cache\FileCache;
use Pg\Router\Cache\Psr16FileCache;
use Pg\Router\Cache\Exception\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

class Psr16FileCacheTest extends TestCase
{
    private string $cacheDir;
    private Psr16FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/pg-router-cache-test' . uniqid();
        $this->cache = new Psr16FileCache($this->cacheDir, true, 'test');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            array_map('unlink', glob($this->cacheDir . '/*.php') ?: []);
            rmdir($this->cacheDir);
        }
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testSetAndGet(): void
    {
        $key = 'test-key';
        $value = ['test' => 'value'];

        $this->assertTrue($this->cache->set($key, $value));
        $result = $this->cache->get($key);

        $this->assertEquals($value, $result);
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testGetWithDefaultValue(): void
    {
        $default = 'default-value';
        $result = $this->cache->get('non-existent-key', $default);

        $this->assertEquals($default, $result);
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testHas(): void
    {
        $key = 'test-key';
        $value = 'test-value';

        $this->assertFalse($this->cache->has($key));
        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testDelete(): void
    {
        $key = 'test-key';
        $value = 'test-value';

        $this->cache->set($key, $value);
        $this->assertTrue($this->cache->has($key));

        $this->assertTrue($this->cache->delete($key));
        $this->assertFalse($this->cache->has($key));
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testSetMultipleAndGetMultiple(): void
    {
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->assertTrue($this->cache->setMultiple($items));
        $result = $this->cache->getMultiple(array_keys($items));

        $this->assertEquals($items, iterator_to_array($result));
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testDeleteMultiple(): void
    {
        $items = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->cache->setMultiple($items);
        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key2']));

        $result = $this->cache->getMultiple(['key1', 'key2'], 'default');
        $this->assertEquals(['key1' => 'default', 'key2' => 'default'], iterator_to_array($result));
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testExpiredItem(): void
    {
        $key = 'test-key';
        $value = 'test-value';

        $this->cache->set($key, $value, 1);
        $this->assertEquals($value, $this->cache->get($key));

        // Sleep for just over 1 second to ensure the item expires
        usleep(2100000); // 2.1 seconds

        $this->assertNull($this->cache->get($key));
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testInvalidKeyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get('invalid/key');
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testFetchMethod(): void
    {
        $key = 'test-fetch';
        $value = 'fetched-value';

        // The first call should call the callback
        $result = $this->cache->fetch($key, function () use ($value) {
            return $value;
        });
        $this->assertEquals($value, $result);

        // Second call should return cached value
        $result = $this->cache->fetch($key, function () {
            $this->fail('Callback should not be called on cache hit');
        });
        $this->assertEquals($value, $result);
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    public function testNamespaceIsolation(): void
    {
        $key = 'same-key';
        $value1 = 'value1';
        $value2 = 'value2';

        $cache1 = new Psr16FileCache($this->cacheDir, true, 'ns1');
        $cache2 = new Psr16FileCache($this->cacheDir, true, 'ns2');

        $cache1->set($key, $value1);
        $cache2->set($key, $value2);

        $this->assertEquals($value1, $cache1->get($key));
        $this->assertEquals($value2, $cache2->get($key));
    }
}
