<?php

namespace PgTest\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Parser\FastMarkParser;
use PHPUnit\Framework\TestCase;

class FastMarkParserTest extends TestCase
{
    protected FastMarkParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FastMarkParser();
    }

    public function testStaticPath()
    {
        $data = $this->parser->parse('/foo/bar');
        $expect = [['/foo/bar'], []];
        $this->assertSame($expect, $data);
    }

    public function testEmptyPath()
    {
        $data = $this->parser->parse('');
        $expect = [['/'], []];
        $this->assertSame($expect, $data);
    }

    public function testRootPath()
    {
        $data = $this->parser->parse('/');
        $expect = [['/'], []];
        $this->assertSame($expect, $data);
    }

    public function testAttributeWithSpecialChars()
    {
        $data = $this->parser->parse('/foo/{b-ar}');
        $expect = [['/foo/([^/]+)'], ['b-ar']];
        $this->assertSame($expect, $data);

        $data = $this->parser->parse('/foo/{b_ar}');
        $expect = [['/foo/([^/]+)'], ['b_ar']];
        $this->assertSame($expect, $data);
    }

    public function testMultipleVariablesInSameSegment()
    {
        $data = $this->parser->parse('/foo/{bar}-{baz}');
        $expect = [['/foo/([^/]+)-([^/]+)'], ['bar', 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testVariableWithDefaultToken()
    {
        $data = $this->parser->parse('/foo/{bar}');
        $expect = [['/foo/([^/]+)'], ['bar']];
        $this->assertSame($expect, $data);
    }

    public function testVariableWithToken()
    {
        $data = $this->parser->parse('/foo/{bar:[a-z]+}');
        $expect = [['/foo/([a-z]+)'], ['bar']];
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariable()
    {
        $data = $this->parser->parse('https://google.com/?q={q}');
        $expect = [['https://google.com/?q=([^/]+)'], ['q']];
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariableWithToken()
    {
        $data = $this->parser->parse('https://google.com/?q={q:[a-z]+}');
        $expect = [['https://google.com/?q=([a-z]+)'], ['q']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithDefaultToken()
    {
        $data = $this->parser->parse('/foo/bar[!/{baz}]');
        $expect = [['/foo/bar', '/foo/bar/([^/]+)'], ['baz']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithToken()
    {
        $data = $this->parser->parse('/foo/bar[!/{baz:[a-z]+}]');
        $expect = [['/foo/bar', '/foo/bar/([a-z]+)'], ['baz']];
        $this->assertSame($expect, $data);
    }

    public function testStaticAndMultipleOptionalsWithToken()
    {
        $data = $this->parser->parse('/foo[!/bar/{bar:[a-z]+};/baz/{baz:\d+}]');
        $expect = [['/foo', '/foo/bar/([a-z]+)', '/foo/bar/([a-z]+)/baz/(\d+)'], ['bar', 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testVariableAndOptionalWithToken()
    {
        $data = $this->parser->parse('/foo/{bar:\d+}[!/{baz:[a-z]+}]');
        $expect = [['/foo/(\d+)', '/foo/(\d+)/([a-z]+)'], ['bar', 'baz']];
        $this->assertSame($expect, $data);
    }

    public function testVariableAndMultipleOptionalsWithToken()
    {
        $data = $this->parser->parse('/foo/{slug:[a-z]+}[!/{bar:[0-9]+};/baz/{baz:\d+}]');
        $expect = [
            ['/foo/([a-z]+)', '/foo/([a-z]+)/([0-9]+)', '/foo/([a-z]+)/([0-9]+)/baz/(\d+)'],
            ['slug', 'bar', 'baz']
        ];
        $this->assertSame($expect, $data);
    }

    public function testOptionalStartPathWithToken()
    {
        $data = $this->parser->parse('[!/{bar:[a-z]+};/test/{test:\w+}]');
        $expect = [['/', '/([a-z]+)', '/([a-z]+)/test/(\w+)'], ['bar', 'test']];
        $this->assertSame($expect, $data);
    }

    public function testVariableAndMultipleOptionalsWithTokenAndSpace()
    {
        $data = $this->parser->parse('/foo/ { slug : [a-z]+ } [! / { bar : [0-9]+ } ; / { baz : \d+ } ]');
        $expect = [
            ['/foo/([a-z]+)', '/foo/([a-z]+)/([0-9]+)', '/foo/([a-z]+)/([0-9]+)/(\d+)'],
            ['slug', 'bar', 'baz']
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
        $this->parser->parse('/foo/{baz:[a-z]+}[!/{bar:[0-9]+};{baz:\d+}]');
    }

    public function testPerformanceWithComplexRoute()
    {
        $complexRoute = '/api/v1/{version:\d+}/users/{user_id:\d+}[!/profile[/{section:[a-z]+};/{subsection:[a-z]+}]]';

        $result = $this->parser->parse($complexRoute);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertIsArray($result[0]); // Routes
        $this->assertIsArray($result[1]); // Variables
    }
}
