<?php

declare(strict_types=1);

namespace PgTest\Router\DuplicateDetector;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Pg\Router\DuplicateDetector\DuplicateMethodMapDetector;
use Pg\Router\Exception\DuplicateRouteException;
use Pg\Router\Route;

class DuplicateMethodMapDetectorTest extends TestCase
{
    private DuplicateMethodMapDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DuplicateMethodMapDetector();
    }

    /**
     * @throws Exception
     */
    private function createRoute(
        string $name,
        string $path,
        ?array $methods = null,
        bool $allowsAny = false
    ): Route {
        $route = $this->createMock(Route::class);
        $route->method('getName')->willReturn($name);
        $route->method('getPath')->willReturn($path);
        $route->method('getAllowedMethods')->willReturn($methods);
        $route->method('allowsAnyMethod')->willReturn($allowsAny);

        return $route;
    }

    /**
     * @throws Exception
     */
    public function testAllowsUniqueRoute(): void
    {
        $route = $this->createRoute('home', '/home', ['GET']);
        $this->detector->detectDuplicate($route);
        $this->assertTrue(true); // No exception expected
    }

    /**
     * @throws Exception
     */
    public function testThrowsExceptionOnDuplicateName(): void
    {
        $route1 = $this->createRoute('route1', '/path', ['GET']);
        $route2 = $this->createRoute('route1', '/other', ['POST']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->expectExceptionMessage('Route with name `route1` already exists');
        $this->detector->detectDuplicate($route2);
    }

    /**
     * @throws Exception
     */
    public function testThrowsExceptionOnDuplicatePathAndMethod(): void
    {
        $route1 = $this->createRoute('route1', '/path', ['GET']);
        $route2 = $this->createRoute('route2', '/path', ['GET']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->expectExceptionMessageMatches('/Route with path `\/path` already exists/');
        $this->detector->detectDuplicate($route2);
    }

    /**
     * @throws Exception
     */
    public function testThrowsExceptionOnAnyMethodConflict(): void
    {
        $route1 = $this->createRoute('route1', '/shared', null, true); // ANY
        $route2 = $this->createRoute('route2', '/shared', ['POST']);

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->expectExceptionMessageMatches('/Route with path `\/shared` already exists with method \[ANY\]/');
        $this->detector->detectDuplicate($route2);
    }

    /**
     * @throws Exception
     */
    public function testAllowsDifferentMethodsOnSamePath(): void
    {
        $route1 = $this->createRoute('route1', '/path', ['GET']);
        $route2 = $this->createRoute('route2', '/path', ['POST']);

        $this->detector->detectDuplicate($route1);
        $this->detector->detectDuplicate($route2);

        $this->assertTrue(true); // No exception expected
    }

    /**
     * @throws Exception
     */
    public function testThrowsExceptionOnAnyOverlappingWithExistingMethods(): void
    {
        $route1 = $this->createRoute('route1', '/conflict', ['GET']);
        $route2 = $this->createRoute('route2', '/conflict', null, true); // ANY

        $this->detector->detectDuplicate($route1);

        $this->expectException(DuplicateRouteException::class);
        $this->expectExceptionMessageMatches('/Route with path `\/conflict` already exists/');
        $this->detector->detectDuplicate($route2);
    }
}
