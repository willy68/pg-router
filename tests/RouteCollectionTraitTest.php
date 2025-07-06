<?php

declare(strict_types=1);

namespace PgTest\Router;

use PHPUnit\Framework\TestCase;

class RouteCollectionTraitTest extends TestCase
{
    private TestRouteCollection $collection;

    public function testGetCallsRouteWithGetMethod()
    {
        $route = $this->collection->get('/foo', 'callback', 'name');
        $this->assertEquals(['GET'], $this->collection->lastMethods);
    }

    public function testPostCallsRouteWithPostMethod()
    {
        $route = $this->collection->post('/bar', 'callback', 'name');
        $this->assertEquals(['POST'], $this->collection->lastMethods);
    }

    public function testAnyCallsRouteWithNullMethods()
    {
        $route = $this->collection->any('/baz', 'callback', 'name');
        $this->assertNull($this->collection->lastMethods);
    }

    public function testPostCallsRouteWithPutMethod()
    {
        $route = $this->collection->put('/bar', 'callback', 'name');
        $this->assertEquals(['PUT'], $this->collection->lastMethods);
    }

    public function testPostCallsRouteWithPatchMethod()
    {
        $route = $this->collection->patch('/bar', 'callback', 'name');
        $this->assertEquals(['PATCH'], $this->collection->lastMethods);
    }

    public function testPostCallsRouteWithDeleteMethod()
    {
        $route = $this->collection->delete('/bar', 'callback', 'name');
        $this->assertEquals(['DELETE'], $this->collection->lastMethods);
    }

    public function testPostCallsRouteWithHeadMethod()
    {
        $route = $this->collection->head('/bar', 'callback', 'name');
        $this->assertEquals(['HEAD'], $this->collection->lastMethods);
    }

    public function testPostCallsRouteWithOptionsMethod()
    {
        $route = $this->collection->options('/bar', 'callback', 'name');
        $this->assertEquals(['OPTIONS'], $this->collection->lastMethods);
    }

    protected function setUp(): void
    {
        $this->collection = new TestRouteCollection();
    }

    // Ajouter d'autres tests pour put, patch, delete, head, options...
}
