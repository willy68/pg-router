<?php

namespace PgTest\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Parser\MarkParser;
use PHPUnit\Framework\TestCase;

class MarkParserTest extends TestCase
{
    protected MarkParser $dataParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataParser = new MarkParser();
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
        $expect = [['/foo/([^/]+)'], [0 => 'bar']];
        $this->assertSame($expect, $data);
    }

    public function testVariableWithToken()
    {
        $data = $this->dataParser->parse('/foo/{bar:[a-z]+}');
        $expect = [['/foo/([a-z]+)'], [0 => 'bar']];
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariable()
    {
        $data = $this->dataParser->parse('http://google.com/?q={q}');
        $expect = [['http://google.com/?q=([^/]+)'], [0 => 'q']];
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariableWithToken()
    {
        $data = $this->dataParser->parse('http://google.com/?q={q:[a-z]+}');
        $expect = [['http://google.com/?q=([a-z]+)'], [0 => 'q']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithDefaultToken()
    {
        $data = $this->dataParser->parse('/foo/bar[/{baz}]');
        $expect = [['/foo/bar', '/foo/bar/([^/]+)'], [0 => 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithToken()
    {
        $data = $this->dataParser->parse('/foo/bar[/{baz:[a-z]+}]');
        $expect = [['/foo/bar', '/foo/bar/([a-z]+)'], [0 => 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndMultipleOptionalsWithToken()
    {
        $data = $this->dataParser->parse('/foo[/{bar:[a-z]+};{baz:\d+}]');
        $expect = [['/foo', '/foo/([a-z]+)', '/foo/([a-z]+)/(\d+)'], [0 => 'bar', 1 => 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testVariableAndOptionalWithToken()
    {
        $data = $this->dataParser->parse('/foo/{bar:\d+}[/{baz:[a-z]+}]');
        $expect = [['/foo/(\d+)', '/foo/(\d+)/([a-z]+)'], [0 => 'bar', 1 => 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testVariableAndMultipleOptionalsWithToken()
    {
        $data = $this->dataParser->parse('/foo/{slug:[a-z]+}[/{bar:[0-9]+};{baz:\d+}]');
        $expect = [
            ['/foo/([a-z]+)', '/foo/([a-z]+)/([0-9]+)', '/foo/([a-z]+)/([0-9]+)/(\d+)'],
            [0 => 'slug', 1 => 'bar', 2 => 'baz']
        ];
        $this->assertSame($expect, $data);
    }

    public function testOptionalStartPathWithToken()
    {
        $data = $this->dataParser->parse('[/{bar:[a-z]+}]');
        $expect = [['/', '/([a-z]+)'], [0 => 'bar']];
        $this->assertSame($expect, $data);
    }
    public function testVariableAndMultipleOptionalsWithTokenAndSpace()
    {
        $data = $this->dataParser->parse('/foo/ { slug : [a-z]+ } [ / { bar : [0-9]+ } ; { baz : \d+ } ]');
        $expect = [
            ['/foo/([a-z]+)', '/foo/([a-z]+)/([0-9]+)', '/foo/([a-z]+)/([0-9]+)/(\d+)'],
            [0 => 'slug', 1 => 'bar', 2 => 'baz']
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
