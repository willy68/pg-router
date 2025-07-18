<?php

declare(strict_types=1);

namespace PgTest\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Parser\NamedParser;
use PHPUnit\Framework\TestCase;

class NamedParserTest extends TestCase
{
    protected NamedParser $dataParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataParser = new NamedParser();
    }
    public function testEmptyPath()
    {
        $data = $this->dataParser->parse('');
        $expect = '/';
        $this->assertSame($expect, $data);
    }

    public function testRootPath()
    {
        $data = $this->dataParser->parse('/');
        $expect = '/';
        $this->assertSame($expect, $data);
    }

    public function testStaticPath()
    {
        $data = $this->dataParser->parse('/foo/bar');
        $expect = '/foo/bar';
        $this->assertSame($expect, $data);
    }

    public function testVariableWithDefaultToken()
    {
        $data = $this->dataParser->parse('/foo/{bar}');
        $expect = '/foo/(?P<bar>[^/]+)';
        $this->assertSame($expect, $data);
    }

    public function testVariableWithDefaultTokenFromRoute()
    {
        $data = $this->dataParser->parse('/foo/{bar}', ['bar' => '[a-zA-Z_-]+']);
        $expect = '/foo/(?P<bar>[a-zA-Z_-]+)';
        $this->assertSame($expect, $data);
    }

    public function testVariableWithToken()
    {
        $data = $this->dataParser->parse('/foo/{bar:[a-z]+}');
        $expect = '/foo/(?P<bar>[a-z]+)';
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariable()
    {
        $data = $this->dataParser->parse('http://google.com/?q={q}');
        $expect = 'http://google.com/?q=(?P<q>[^/]+)';
        $this->assertSame($expect, $data);
    }

    public function testFullUriVariableWithToken()
    {
        $data = $this->dataParser->parse('http://google.com/?q={q:[a-z]+}');
        $expect = 'http://google.com/?q=(?P<q>[a-z]+)';
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithDefaultToken()
    {
        $data = $this->dataParser->parse('/foo/bar[!/{baz}]');
        $expect = '/foo/bar(?:/(?P<baz>[^/]+))?';
        $this->assertSame($expect, $data);
    }

    public function testStaticAndOptionalWithToken()
    {
        $data = $this->dataParser->parse('/foo/bar[!/{baz:[a-z]+}]');
        $expect = '/foo/bar(?:/(?P<baz>[a-z]+))?';
        $this->assertSame($expect, $data);
    }

    public function testStaticAndMultipleOptionalsWithToken()
    {
        $data = $this->dataParser->parse('/foo[!/bar/{bar:[a-z]+};/baz/{baz:\d+}]');
        $expect = '/foo(?:/bar/(?P<bar>[a-z]+)(?:/baz/(?P<baz>\d+))?)?';
        $this->assertSame($expect, $data);
    }

    public function testVariableAndOptionalWithToken()
    {
        $data = $this->dataParser->parse('/foo/{bar:\d+}[!/{baz:[a-z]+}]');
        $expect = '/foo/(?P<bar>\d+)(?:/(?P<baz>[a-z]+))?';
        $this->assertSame($expect, $data);
    }

    public function testVariableAndMultipleOptionalsWithToken()
    {
        $data = $this->dataParser->parse('/foo/{slug:[a-z]+}[!/{bar:[0-9]+};/{baz:\d+}]');
        $expect = '/foo/(?P<slug>[a-z]+)(?:/(?P<bar>[0-9]+)(?:/(?P<baz>\d+))?)?';
        $this->assertSame($expect, $data);
    }

    public function testOptionalStartPathWithToken()
    {
        $data = $this->dataParser->parse('[!/ {bar:[a-z]+};/test/{test:\w+}]');
        $expect = '/(?:(?P<bar>[a-z]+)(?:/test/(?P<test>\w+))?)?';
        $this->assertSame($expect, $data);
    }
    public function testVariableAndMultipleOptionalsWithTokenAndSpace()
    {
        $data = $this->dataParser->parse('/foo/ { slug : [a-z]+ } [! / { bar : [0-9]+ } ; / { baz : \d+ } ]');
        $expect = '/foo/(?P<slug>[a-z]+)(?:/(?P<bar>[0-9]+)(?:/(?P<baz>\d+))?)?';
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
        $this->dataParser->parse('/foo/{baz:[a-z]+}[!/{bar:[0-9]+};/{baz:\d+}]');
    }
}
