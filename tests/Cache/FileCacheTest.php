<?php

namespace Pg\Router\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Pg\Router\Cache\FileCache;

class FileCacheTest extends TestCase
{
    private $cacheDir;
    private $fileCache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/pg-router-test-cache';
        $this->fileCache = new FileCache($this->cacheDir, true);
    }

    protected function tearDown(): void
    {
        // Suppression du dossier de test après chaque test
        if (file_exists($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*.php');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->cacheDir);
        }
    }

    public function testSetAndGet(): void
    {
        $key = 'test-key';
        $value = ['test' => 'value'];
        
        $this->fileCache->set($key, $value);
        $result = $this->fileCache->get($key);
        
        $this->assertNotNull($result);
        $this->assertEquals($value, $result);
    }

    public function testHas(): void
    {
        $key = 'test-key';
        $value = ['test' => 'value'];
        
        $this->assertFalse($this->fileCache->has($key));
        
        $this->fileCache->set($key, $value);
        $this->assertTrue($this->fileCache->has($key));
    }

    public function testDelete(): void
    {
        $key = 'test-key';
        $value = ['test' => 'value'];
        
        $this->fileCache->set($key, $value);
        $this->assertTrue($this->fileCache->has($key));
        
        $this->fileCache->delete($key);
        $this->assertFalse($this->fileCache->has($key));
    }

    public function testClear(): void
    {
        $key1 = 'test-key1';
        $key2 = 'test-key2';
        $value = ['test' => 'value'];
        
        $this->fileCache->set($key1, $value);
        $this->fileCache->set($key2, $value);
        
        $this->fileCache->clear();
        
        $this->assertFalse($this->fileCache->has($key1));
        $this->assertFalse($this->fileCache->has($key2));
    }

    public function testCacheDisabled(): void
    {
        $fileCache = new FileCache($this->cacheDir, false);
        $key = 'test-key';
        $value = ['test' => 'value'];
        
        $fileCache->set($key, $value);
        $this->assertNull($fileCache->get($key));
        $this->assertFalse($fileCache->has($key));
    }

    public function testFetch(): void
    {
        $key = 'test-key';
        $value = ['test' => 'value'];
        
        $fileCache = new FileCache($this->cacheDir, true);

        // First fetch should call the data fetcher
        $result = $fileCache->fetch($key,function () use ($value) {
            return $value;
        });
        $this->assertEquals($value, $result);
        
        // Second fetch should use cache
        $result = $fileCache->fetch($key,function () use ($value) {
            return $value;
        });
        $this->assertEquals($value, $result);
    }
}
