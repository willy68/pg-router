<?php

namespace PgTest\Router\Matcher;

use GuzzleHttp\Psr7\ServerRequest;
use Pg\Router\Matcher\RequestMatcher;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class RequestMatcherTest extends TestCase
{
    public function testMatchesStaticPath()
    {
        $matcher = new RequestMatcher('^/users', ['GET']);
        $request = new ServerRequest('GET', '/users/123');

        $this->assertTrue($matcher->match($request));
    }

    public function testMatchesSimpleMethodAndPath()
    {
        $matcher = new RequestMatcher('/users/{id}', ['GET']);
        $request = new ServerRequest('GET', '/users/123');

        $this->assertTrue($matcher->match($request));
        $this->assertSame(['id' => '123'], $matcher->getPathParams());
    }

    public function testMatchesSimpleRegexPath()
    {
        $matcher = new RequestMatcher('/users/(\d+)', ['GET']);
        $request = new ServerRequest('GET', '/users/123');

        $this->assertTrue($matcher->match($request));
    }

    public function testMatchesSimpleAttributeAndRegexPath()
    {
        $matcher = new RequestMatcher('/users/{id:\d+}', ['GET']);
        $request = new ServerRequest('GET', '/users/123');

        $this->assertTrue($matcher->match($request));
        $this->assertSame(['id' => '123'], $matcher->getPathParams());
    }

    public function testMatchesSimpleWrongRegexPath()
    {
        $matcher = new RequestMatcher('/users/(\W+)', ['GET']);
        $request = new ServerRequest('GET', '/users/123');

        $this->assertFalse($matcher->match($request));
    }

    public function testDoesNotMatchWrongMethod()
    {
        $matcher = new RequestMatcher('/users/{id}', ['POST']);
        $request = new ServerRequest('GET', '/users/123');

        $this->assertFalse($matcher->match($request));
    }

    public function testDoesNotMatchWrongPath()
    {
        $matcher = new RequestMatcher('/users/{id}', ['GET']);
        $request = new ServerRequest('GET', '/profile/123');

        $this->assertFalse($matcher->match($request));
    }

    public function testMatchesWithMultipleParameters()
    {
        $matcher = new RequestMatcher('/users/{userId}/posts/{postId}', ['GET']);
        $request = new ServerRequest('GET', '/users/5/posts/42');

        $this->assertTrue($matcher->match($request));
        $this->assertSame(['userId' => '5', 'postId' => '42'], $matcher->getPathParams());
    }

    public function testMatchesWithMultipleParametersAndAttributesWithSpaces()
    {
        $matcher = new RequestMatcher('/users/{ userId: \d+ }/posts/{ postId: \d+ }', ['GET']);
        $request = new ServerRequest('GET', '/users/5/posts/42');

        $this->assertTrue($matcher->match($request));
        $this->assertSame(['userId' => '5', 'postId' => '42'], $matcher->getPathParams());
    }

    public function testDoesNotMatchWrongScheme()
    {
        $matcher = new RequestMatcher('/users/{id}', ['GET'], null, ['https']);
        $request = new ServerRequest('GET', '/profile/123');
        $request = $request->withUri($request->getUri()->withScheme('http'));

        $this->assertFalse($matcher->match($request));
    }

    public function testDoesNotMatchWrongHost()
    {
        $matcher = new RequestMatcher('/users/{id}', ['GET'], 'example.com', ['https']);
        $request = new ServerRequest('GET', '/profile/123');
        $request = $request->withUri($request->getUri()->withScheme('https')->withHost('example.org'));

        $this->assertFalse($matcher->match($request));
    }

    public function testDoesNotMatchWrongPort()
    {
        $matcher = new RequestMatcher('/users/{id}', ['GET'], 'example.com', ['https'], '80');
        $request = new ServerRequest('GET', '/profile/123');
        $request = $request->withUri(
            $request->getUri()
                ->withScheme('https')
                ->withHost('example.com')
                ->withPort(443)
        );

        $this->assertFalse($matcher->match($request));
    }
}
