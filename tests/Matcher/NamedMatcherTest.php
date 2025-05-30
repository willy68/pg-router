<?php

declare(strict_types=1);

namespace PgTest\Router\Matcher;

use PHPUnit\Framework\TestCase;
use Pg\Router\Matcher\NamedMatcher;
use ReflectionException;

class NamedMatcherTest extends TestCase
{
    public function testMatchPathReturnsMatchesAndSetsMatchedRoute()
    {
        $matcher = new NamedMatcher([
            'GET' => [
                'user_show' => [
                    'regex' => '/user/(?P<id>\d+)',
                ],
            ]
        ]);
        $uri = '/user/42';
        $result = $matcher->match($uri, 'GET');

        $this->assertIsArray($result);
        $this->assertSame('user_show', $matcher->getMatchedRouteName());
        $this->assertSame(['id' => '42'], $matcher->getAttributes());
    }

    public function testMatchPathReturnsFalseOnNoMatch()
    {
        $matcher = new NamedMatcher([
            'GET' => [
                'user_show' => [
                    'regex' => '/user/(?P<id>\d+)',
                ],
            ]
        ]);
        $uri = '/profile/42';

        $result = $matcher->match($uri, 'GET');

        $this->assertFalse($result);
        $this->assertNull($matcher->getMatchedRouteName());
        $this->assertSame([], $matcher->getAttributes());
    }

    /**
     * @throws ReflectionException
     */
    public function testFoundAttributesDecodesAndFilters()
    {
        $matcher = new NamedMatcher();
        $matches = [
            0 => '/user/42',
            'id' => '42',
            'empty' => '',
            'encoded' => rawurlencode('foo bar'),
        ];

        $attributes = $this->invokeProtectedMethod($matcher, 'foundAttributes', [$matches]);

        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('id', $attributes);
        $this->assertSame('42', $attributes['id']);
        $this->assertArrayHasKey('encoded', $attributes);
        $this->assertSame('foo bar', $attributes['encoded']);
        $this->assertArrayNotHasKey('empty', $attributes);
    }

    public function testMethodNotAllowed(): void
    {
        $matcher = new NamedMatcher([
            'GET' => [
                'user_show' => [
                    'regex' => '/user/(?P<id>\d+)',
                ],
            ]
        ]);

        $result = $matcher->match('/user/5', 'POST');

        $this->assertFalse($result);
        $this->assertSame(['user_show'], $matcher->getFailedRoutesMethod());
        $this->assertEquals(['GET'], $matcher->getAllowedMethods());
    }

    /**
     * @throws ReflectionException
     */
    private function invokeProtectedMethod(object $object, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
