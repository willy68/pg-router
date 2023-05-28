<?php

namespace PgTest\Router\RegexCollector;

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

    protected function getCallback(): \Closure
    {
        return fn () => 'callback';
    }

    public function testOnlyStaticPathWithMethodGet()
    {
        $route = new Route('/foo', $this->getCallback(), 'test', ['GET']);
        $this->collector->addRoute($route);

        $expected = ['GET' => [['regex' => '~^(?|/foo(*MARK:test))$~x', 'routeVars' => ['test' => ['vars' => []]]]]];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }

    public function testOnlyStaticPathWithMethodAny()
    {
        $route = new Route('/foo', $this->getCallback(), 'test');
        $this->collector->addRoute($route);

        $expected = ['ANY' => [['regex' => '~^(?|/foo(*MARK:test))$~x', 'routeVars' => ['test' => ['vars' => []]]]]];

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
                    'routeVars' => ['test' => ['vars' => ['bar' => 'bar']]]
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
                    'routeVars' => ['test' => ['vars' => ['bar' => 'bar']]]
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
                    'routeVars' => ['test' => ['vars' => ['bar' => 'bar', 'baz' => 'baz']]]
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
                    'routeVars' => ['test' => ['vars' => ['bar' => 'bar', 'baz' => 'baz']]]
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
                    'routeVars' => ['test' => ['vars' => ['bar' => 'bar', 'baz' => 'baz', 'raz' => 'raz']]]
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
                    'routeVars' => ['test' => ['vars' => ['baz' => 'baz', 'raz' => 'raz']]]
                ]
            ]
        ];

        $data = $this->collector->getData();
        $this->assertSame($expected, $data);
    }
}
