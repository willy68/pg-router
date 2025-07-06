<?php

namespace PgTest\Router;

use Pg\Router\Route;
use Pg\Router\RouteCollectionTrait;

class TestRouteCollection
{
    use RouteCollectionTrait;

    public array|null $lastMethods = null;

    public function route(
        string $path,
        callable|array|string $callback,
        ?string $name = null,
        ?array $methods = null
    ): Route {
        $this->lastMethods = $methods;
        // Retourne un objet Route factice pour le test
        return new Route($path, $callback, $name, $methods);
    }
}
