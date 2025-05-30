<?php

namespace PgTest\Router\Matcher;

use Pg\Router\Matcher\MarkDataMatcher;
use PHPUnit\Framework\TestCase;

class MarkDataMatcherTest extends TestCase
{
    private function getMatcher(): MarkDataMatcher
    {
        return new MarkDataMatcher([
            'GET' => [
                [
                    'regex' => '~^(?|/blog/category/([0-9]+)/post/([0-9]+)(*MARK:profile))$~x',
                    'attributes' => [
                        'profile' => ['category_id', 'id']
                    ]
                ]
            ],
            'POST' => [
                [
                    'regex' => '~^(?|/submit/form/(\w+)(*MARK:form))$~x',
                    'attributes' => [
                        'form' => ['token']
                    ]
                ]
            ],
            'ANY' => [
                [
                    'regex' => '~^(?|/fallback/test(*MARK:test))$~x',
                    'attributes' => [
                        'test' => []
                    ]
                ]
            ]
        ]);
    }

    public function testMatchWithCorrectGetMethod(): void
    {
        $matcher = $this->getMatcher();
        $result = $matcher->match('/blog/category/3/post/5', 'GET');

        $this->assertIsArray($result);
        $this->assertEquals(['profile' => 'GET', ['category_id' => '3', 'id' => '5']], $result);
        $this->assertEquals('profile', $matcher->getMatchedRouteName());
        $this->assertEquals(['category_id' => '3', 'id' => '5'], $matcher->getAttributes());
    }

    public function testMatchWithFallbackAnyMethod(): void
    {
        $matcher = $this->getMatcher();
        $result = $matcher->match('/fallback/test', 'PUT');

        $this->assertIsArray($result);
        $this->assertEquals(['test' => 'PUT', []], $result);
    }

    public function testMethodNotAllowed(): void
    {
        $matcher = $this->getMatcher();
        $result = $matcher->match('/submit/form/xyz', 'GET'); // GET not allowed for /submit

        $this->assertFalse($result);
        $this->assertEquals(['form'], $matcher->getFailedRoutesMethod());
        $this->assertEquals(['POST'], $matcher->getAllowedMethods());
    }

    public function testNoMatch(): void
    {
        $matcher = $this->getMatcher();
        $result = $matcher->match('/invalid/path', 'GET');

        $this->assertFalse($result);
        $this->assertEmpty($matcher->getFailedRoutesMethod());
        $this->assertEmpty($matcher->getAllowedMethods());
    }
}
