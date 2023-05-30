<?php

namespace PgTest\Router\RegexCollector;

use Closure;
use Pg\Router\RegexCollector\MarkRegexCollector;
use Pg\Router\Route;
use PHPUnit\Framework\TestCase;

class MarkRegexCollectorTest extends TestCase
{
    protected MarkRegexCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new MarkRegexCollector();
    }

    protected function getCallback(): Closure
    {
        return fn() => 'callback';
    }

    public function testOnlyStaticPathWithMethodGet()
    {
        $route = new Route('/foo', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = ['GET' => [['regex' => '~^(?|/foo(*MARK:test))$~x', 'attributes' => ['test' => []]]]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testOnlyStaticPathWithMethodAny()
    {
        $route = new Route('/foo', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = ['ANY' => [['regex' => '~^(?|/foo(*MARK:test))$~x', 'attributes' => ['test' => []]]]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariablePartPathWithMethodGet()
    {
        $route = new Route('/foo/{bar:[a-z]+}', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar' => 'bar']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testPathWithVariablePartWith2Method()
    {
        $route = new Route('/foo/{bar:[a-z]+}', $this->getCallback(), 'test', ['GET', 'POST']);
        $this->collector->addRoute($route);

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar' => 'bar']]
                ]
            ],
            'POST' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar' => 'bar']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testPathWithVariablePartWithMethodAny()
    {
        $route = new Route('/foo/{bar:[a-z]+}', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = [
            'ANY' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar' => 'bar']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testPathWithVariableAndOptionalPartsWithMethodGet()
    {
        $route = new Route('/foo/{bar:[a-z]+}[/{baz:\d+}]', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test)|/foo/([a-z]+)/(\d+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar' => 'bar', 'baz' => 'baz']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariableAndOptionalPartsPathWithMethodAny()
    {
        $route = new Route('/foo/{bar:[a-z]+}[/{baz:\d+}]', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = [
            'ANY' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test)|/foo/([a-z]+)/(\d+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar' => 'bar', 'baz' => 'baz']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariableAndMultipleOptionalPartsPathWithMethodGet()
    {
        $route = new Route(
            '/foo/{bar:[a-z]+}[/{baz:\d+};{raz:[a-z]+}]',
            $this->getCallback(),
            'test',
            ['GET']
        );
        $this->collector->addRoute($route);

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test)|' .
                        '/foo/([a-z]+)/(\d+)(*MARK:test)|' .
                        '/foo/([a-z]+)/(\d+)/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar' => 'bar', 'baz' => 'baz', 'raz' => 'raz']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testPathStartWithMultipleOptionalPartsWithMethodGet()
    {
        $route = new Route(
            '[/{baz:\d+};{raz:[a-z]+}]',
            $this->getCallback(),
            'test',
            ['GET']
        );
        $this->collector->addRoute($route);

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/(*MARK:test)|' .
                        '/(\d+)(*MARK:test)|' .
                        '/(\d+)/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['baz' => 'baz', 'raz' => 'raz']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testChunk()
    {
        $toChunk = [];
        $expected = [];
        $callback = fn ($request) => $request->getAttribute('id');
        for ($i = 0; $i <= 20; $i++) {
            $this->collector->addRoute(
                new Route(
                    "/foo$i/{bar:\d+}[/{baz:[a-z]+}]",
                    $callback,
                    'test' . $i,
                    ['GET']
                )
            );
            $attributes = ['bar' => 'bar', 'baz' => 'baz'];
            $toChunk['GET']["test$i"] = ["/foo$i/(\d+)(*MARK:test$i)|/foo$i/(\d+)/([a-z]+)(*MARK:test$i)",$attributes];
        }

        foreach ($toChunk as $method => $route) {
            $chunk = array_chunk($route, 15, true);
            $expected[$method] = array_map([$this, 'computeRegexData'], $chunk);
        }

        $data = $this->collector->getData();
        $this->assertCount(2, $data['GET']);
        $this->assertCount(15, $data['GET'][0]['attributes']);
        $this->assertCount(6, $data['GET'][1]['attributes']);
        $this->assertSame($expected, $data);
    }

    protected function computeRegexData(array $routes): array
    {
        $regexes = [];
        $attributes = [];

        foreach ($routes as $name => $route) {
            [$regex, $vars] = $route;
            $attributes[$name] = $vars;
            $regexes[] = $regex;
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~x';

        return ['regex' => $regex, 'attributes' => $attributes];
    }
}
