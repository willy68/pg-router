<?php

namespace PgTest\Router\Generator;

use Pg\Router\Exception\MissingAttributeException;
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

    /**
     * @throws \Exception
     */
    public function testGenerateStatic()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog/edit', 'foo', 'test');

        $url = $generator->generate('test', []);
        $this->assertEquals('/blog/edit', $url);
    }

    /**
     * @throws \Exception
     */
    public function testGenerateWithInlineToken()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog/{id:([0-9]+)}/edit', 'foo', 'test');

        $url = $generator->generate('test', ['id' => 42, 'foo' => 'bar']);
        $this->assertEquals('/blog/42/edit', $url);
    }

    /**
     * @throws \Exception
     */
    public function testGenerateWithInlineParamWithoutToken()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog/{id}/edit', 'foo', 'test');

        $url = $generator->generate('test', ['id' => 'id', 'foo' => 'bar']);
        $this->assertEquals('/blog/id/edit', $url);
    }

    /**
     * @throws \Exception
     */
    public function testGenerateMatchingException()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog/{id:([0-9]+)}/edit', 'foo', 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `([0-9]+)`');
        $generator->generate('test', ['id' => '4 2', 'foo' => 'bar']);
    }

    /**
     * @throws \Exception
     */
    public function testGenerateMatchingExceptionForOptional()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog[!/{id:([0-9]+)}]', 'foo', 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `([0-9]+)`');
        $generator->generate('test', ['id' => '4 2', 'foo' => 'bar']);
    }

    /**
     * @throws \Exception
     */
    public function testGenerateMissingAttribute()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/blog/{id:([0-9]+)}/edit', 'foo', 'test');

        $this->expectException(MissingAttributeException::class);
        $this->expectExceptionMessage('Parameter value for [id] is missing for route [test]');
        $generator->generate('test');
    }

    /**
     * @throws \Exception
     */
    public function testGenerateMissing()
    {
        $collector = $this->getCollector();
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage(sprintf('Route with name [%s] not found', 'no-such-route'));
        $this->getGenerator($collector)->generate('no-such-route');
    }

    /**
     * @throws \Exception
     */
    public function testGenerateWithOptionalAndSpace()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('/archive/{ category }[! / { year }; / { month } ;/{day}]', 'foo', 'test')
            ->setTokens(['year' => '[0-9]{4}', 'month' => '[0-9]{2}', 'day' => '[0-9]{2}']);

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

    /**
     * @throws \Exception
     */
    public function testOptionalStartPathWithToken()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('[!/{bar:[a-z]+};/test/{test:\w+}]', 'foo', 'test');

        $url = $generator->generate('test', []);
        $this->assertEquals('/', $url);

        $url = $generator->generate('test', ['bar' => 'foo']);
        $this->assertEquals('/foo', $url);

        $url = $generator->generate('test', ['bar' => 'foo', 'test' => 'baz']);
        $this->assertEquals('/foo/test/baz', $url);
    }

    /**
     * @throws \Exception
     */
    public function testOptionalStartPathWithoutFirstSlashAndSpace()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('[! { bar: [a-z]+ };/test/{ test: \w+ } ]', 'foo', 'test');

        $url = $generator->generate('test', []);
        $this->assertEquals('/', $url);

        $url = $generator->generate('test', ['bar' => 'foo']);
        $this->assertEquals('/foo', $url);

        $url = $generator->generate('test', ['bar' => 'foo', 'test' => 'baz']);
        $this->assertEquals('/foo/test/baz', $url);
    }

    /**
     * @throws \Exception
     */
    public function testGenerateOnFullUri()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('https://google.com/?q={q}', ['action', 'google-search'], 'test');

        $actual = $generator->generate('test', ['q' => "what's up doc?"]);
        $expect = "https://google.com/?q=what%27s%20up%20doc%3F";
        $this->assertSame($expect, $actual);
    }

    /**
     * @throws \Exception
     */
    public function testGenerateWithHost()
    {
        $collector = $this->getCollector();
        $generator = $this->getGenerator($collector);
        $collector->route('//{host}.example.com/blog/{id}/edit', 'foo', 'test');

        $url = $generator->generate('test', ['id' => 42, 'host' => 'bar']);
        $this->assertEquals('//bar.example.com/blog/42/edit', $url);
    }
}
