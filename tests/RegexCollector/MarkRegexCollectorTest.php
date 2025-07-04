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

    public function testPathWithVariablePartWithMethodGet()
    {
        $route = new Route('/foo/{bar:[a-z]+}', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar']]
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
                    'attributes' => ['test' => ['bar']]
                ]
            ],
            'POST' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar']]
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
                    'attributes' => ['test' => ['bar']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testPathWithVariableAndOptionalPartsWithMethodGet()
    {
        $route = new Route('/foo/{bar:[a-z]+}[!/{baz:\d+}]', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test)|/foo/([a-z]+)/(\d+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar', 'baz']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testWithVariableAndOptionalPartsPathWithMethodAny()
    {
        $route = new Route('/foo/{bar:[a-z]+}[!/{baz:\d+}]', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = [
            'ANY' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test)|/foo/([a-z]+)/(\d+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar', 'baz']]
                ]
            ]
        ];

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

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/foo/([a-z]+)(*MARK:test)|' .
                        '/foo/([a-z]+)/(\d+)(*MARK:test)|' .
                        '/foo/([a-z]+)/(\d+)/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['bar', 'baz', 'raz']]
                ]
            ]
        ];

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

        $expected = [
            'GET' => [
                [
                    'regex' => '~^(?|/(*MARK:test)|' .
                        '/(\d+)(*MARK:test)|' .
                        '/(\d+)/([a-z]+)(*MARK:test))$~x',
                    'attributes' => ['test' => ['baz', 'raz']]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testChunk()
    {
        $callback = fn ($request) => $request->getAttribute('id');
        for ($i = 0; $i <= 20; $i++) {
            $this->collector->addRoute(
                new Route(
                    "/foo$i/{bar:\d+}[!/{baz:[a-z]+}]",
                    $callback,
                    'test' . $i,
                    ['GET']
                )
            );
        }

        $data = $this->collector->getData();
        $this->assertCount(2, $data['GET']);
        $this->assertCount(15, $data['GET'][0]['attributes']);
        $this->assertCount(6, $data['GET'][1]['attributes']);
    }
}
