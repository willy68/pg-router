<?php

namespace PgTest\Router\RegexCollector;

use Closure;
use Pg\Router\RegexCollector\NamedRegexCollector;
use Pg\Router\Route;
use PHPUnit\Framework\TestCase;

class NamedRegexCollectorTest extends TestCase
{
    protected NamedRegexCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new NamedRegexCollector();
    }

    protected function getCallback(): Closure
    {
        return fn () => 'callback';
    }

    public function testOnlyStaticPathWithMethodGet()
    {
        $route = new Route('/foo', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = ['GET' => ['test' => ['regex' => '/foo']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testOnlyStaticPathWithMethodAny()
    {
        $route = new Route('/foo', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = ['ANY' => ['test' => ['regex' => '/foo']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariablePartPathWithMethodGet()
    {
        $route = new Route('/foo/{bar:[a-z]+}', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = ['GET' => ['test' => ['regex' => '/foo/(?P<bar>[a-z]+)']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariablePartPathWithMethodAny()
    {
        $route = new Route('/foo/{bar:[a-z]+}', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = ['ANY' => ['test' => ['regex' => '/foo/(?P<bar>[a-z]+)']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariableAndOptionalPartsPathWithMethodGet()
    {
        $route = new Route('/foo/{bar:[a-z]+}[!/{baz:\d+}]', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = ['GET' => ['test' => ['regex' => '/foo/(?P<bar>[a-z]+)(?:/(?P<baz>\d+))?']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariableAndOptionalPartsPathWithMethodAny()
    {
        $route = new Route('/foo/{bar:[a-z]+}[!/{baz:\d+}]', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = ['ANY' => ['test' => ['regex' => '/foo/(?P<bar>[a-z]+)(?:/(?P<baz>\d+))?']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariableAndMultipleOptionalPartsPathWithMethodGet()
    {
        $route = new Route(
            '/foo/{bar:[a-z]+}[!/{baz:\d+};/{raz:[a-z]+}]',
            $this->getCallback(),
            'test',
            ['GET']
        );
        $this->collector->addRoute($route);

        $expected = ['GET' => ['test' => ['regex' => '/foo/(?P<bar>[a-z]+)(?:/(?P<baz>\d+)(?:/(?P<raz>[a-z]+))?)?']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testPathStartWithMultipleOptionalPartsWithMethodGet()
    {
        $route = new Route(
            '[!/{baz:\d+};/{raz:[a-z]+}]',
            $this->getCallback(),
            'test',
            ['GET']
        );
        $this->collector->addRoute($route);

        $expected = ['GET' => ['test' => ['regex' => '/(?:(?P<baz>\d+)(?:/(?P<raz>[a-z]+))?)?']]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testRoutesCollectionEmpty()
    {
        $route = new Route(
            '/foo/{bar:[a-z]+}[!/{baz:\d+};/{raz:[a-z]+}]',
            $this->getCallback(),
            'test',
            ['GET']
        );
        $this->collector->addRoute($route);

        $reflection = new \ReflectionClass($this->collector);
        $property = $reflection->getProperty('routes');
        $this->assertNotEmpty($property->getValue($this->collector));

        $this->collector->getData();
        $this->assertEmpty($property->getValue($this->collector));
    }
}
