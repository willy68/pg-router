<?php

namespace Generator;

use Pg\Router\Exception\RouteNotFoundException;
use Pg\Router\Generator\UrlGenerator;
use Pg\Router\RouteCollector;
use Pg\Router\Router;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UrlGeneratorTest extends TestCase
{
    protected Router|MockObject $router;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $router = $this->createMock(Router::class);
        $this->router = $router;
    }

    protected function getCollector(): RouteCollector
    {
        return new RouteCollector($this->router);
    }

    protected function getGenerator(RouteCollector $collector): UrlGenerator
    {
        return new UrlGenerator($collector);
    }

    public function testGenerateWithInlineToken()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog/{id:([0-9]+)}/edit', 'foo', 'test');

        $url = $generator->generate('test', ['id' => 42, 'foo' => 'bar']);
        $this->assertEquals('/blog/42/edit', $url);
    }

    public function testGenerateMatchingException()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog/{id:([0-9]+)}/edit', 'foo', 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `([0-9]+)`');
        $url = $generator->generate('test', ['id' => '4 2', 'foo' => 'bar']);
    }

    public function testGenerateMissing()
    {
        $collector = $this->getCollector();
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Route with name [%s] not found', 'no-such-route'));
        $this->getGenerator($collector)->generate('no-such-route');
    }

    public function testGenerateWithOptional()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/archive/{category}[/{year};{month};{day}]', 'foo', 'test');

        // some
        $url = $generator->generate('test', [
            'category' => 'foo',
            'year' => '1979',
            'month' => '11',
        ]);
        $this->assertEquals('/archive/foo/1979/11', $url);

        // all
        $url = $generator->generate('test', [
            'category' => 'foo',
            'year' => '1979',
            'month' => '11',
            'day' => '07',
        ]);
        $this->assertEquals('/archive/foo/1979/11/07', $url);
    }

    public function testGenerateOnFullUri()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('http://google.com/?q={q}', ['action', 'google-search'], 'test');

        $actual = $generator->generate('test', ['q' => "what's up doc?"]);
        $expect = "http://google.com/?q=what%27s%20up%20doc%3F";
        $this->assertSame($expect, $actual);
    }

    public function testGenerateWithHost()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('//{host}.example.com/blog/{id}/edit', 'foo', 'test');

        $url = $generator->generate('test', ['id' => 42, 'host' => 'bar']);
        $this->assertEquals('//bar.example.com/blog/42/edit', $url);
    }

}
