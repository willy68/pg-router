<?php

namespace PgTest\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Parser\NamedParamsParser;
use PHPUnit\Framework\TestCase;

class NamedParamsParserTest extends TestCase
{
    protected NamedParamsParser $dataParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataParser = new NamedParamsParser();
    }

    public function testStaticPath()
    {
        $data = $this->dataParser->parse('/foo/bar');
        $expect = [['/foo/bar'], []];
        $this->assertSame($expect, $data);
    }

    public function testVariableWithDefaultToken()
    {
        $data = $this->dataParser->parse('/foo/{bar}');
        $expect = [['/foo/(?P<bar>[^/]+)'], ['bar' => 'bar']];
        $this->assertSame($expect, $data);
    }

    public function testVariableWithToken()
    {
        $data = $this->dataParser->parse('/foo/{bar:[a-z]+}');
        $expect = [['/foo/(?P<bar>[a-z]+)'], ['bar' => 'bar']];
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariable()
    {
        $data = $this->dataParser->parse('http://google.com/?q={q}');
        $expect = [['http://google.com/?q=(?P<q>[^/]+)'], ['q' => 'q']];
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariableWithToken()
    {
        $data = $this->dataParser->parse('http://google.com/?q={q:[a-z]+}');
        $expect = [['http://google.com/?q=(?P<q>[a-z]+)'], ['q' => 'q']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithDefaultToken()
    {
        $data = $this->dataParser->parse('/foo/bar[/{baz}]');
        $expect = [['/foo/bar', '/foo/bar/(?P<baz>[^/]+)'], ['baz' => 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithToken()
    {
        $data = $this->dataParser->parse('/foo/bar[/{baz:[a-z]+}]');
        $expect = [['/foo/bar', '/foo/bar/(?P<baz>[a-z]+)'], ['baz' => 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndMultipleOptionalsWithToken()
    {
        $data = $this->dataParser->parse('/foo[/{bar:[a-z]+};{baz:\d+}]');
        $expect = [
            ['/foo', '/foo/(?P<bar>[a-z]+)', '/foo/(?P<bar>[a-z]+)/(?P<baz>\d+)'],
            ['bar' => 'bar', 'baz' => 'baz']
        ];
        $this->assertSame($expect, $data);
    }

    public function testVariableAndOptionalWithToken()
    {
        $data = $this->dataParser->parse('/foo/{bar:\d+}[/{baz:[a-z]+}]');
        $expect = [['/foo/(?P<bar>\d+)', '/foo/(?P<bar>\d+)/(?P<baz>[a-z]+)'], ['bar' => 'bar', 'baz' => 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testVariableAndMultipleOptionalsWithToken()
    {
        $data = $this->dataParser->parse('/foo/{slug:[a-z]+}[/{bar:[0-9]+};{baz:\d+}]');
        $expect = [
            [
                '/foo/(?P<slug>[a-z]+)', '/foo/(?P<slug>[a-z]+)/(?P<bar>[0-9]+)',
                '/foo/(?P<slug>[a-z]+)/(?P<bar>[0-9]+)/(?P<baz>\d+)'
            ],
            ['slug' => 'slug', 'bar' => 'bar', 'baz' => 'baz']
        ];
        $this->assertSame($expect, $data);
    }

    public function testOptionalStartPathWithToken()
    {
        $data = $this->dataParser->parse('[/{bar:[a-z]+}]');
        $expect = [['/', '/(?P<bar>[a-z]+)'], ['bar' => 'bar']];
        $this->assertSame($expect, $data);
    }
    public function testVariableAndMultipleOptionalsWithTokenAndSpace()
    {
        $data = $this->dataParser->parse('/foo/ { slug : [a-z]+ } [ / { bar : [0-9]+ } ; { baz : \d+ } ]');
        $expect = [
            [
                '/foo/(?P<slug>[a-z]+)', '/foo/(?P<slug>[a-z]+)/(?P<bar>[0-9]+)',
                '/foo/(?P<slug>[a-z]+)/(?P<bar>[0-9]+)/(?P<baz>\d+)'
            ],
            ['slug' => 'slug', 'bar' => 'bar', 'baz' => 'baz']
        ];
        $this->assertSame($expect, $data);
    }

    public function testExpectExceptionWithAttributeTwice()
    {
        $this->expectException(DuplicateAttributeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Cannot use the same attribute twice [%s]',
                'baz'
            )
        );
        $this->dataParser->parse('/foo/{baz:[a-z]+}[/{bar:[0-9]+};{baz:\d+}]');
    }
}
