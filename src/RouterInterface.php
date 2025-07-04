<?php

declare(strict_types=1);

namespace Pg\Router;

use Exception;
use Pg\Router\Exception\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Interface defining required router capabilities.
 */
interface RouterInterface
{
    /**
     * Add a route.
     *
     * This method adds a route against which the underlying implementation may
     * match. Implementations 'MUST' aggregate route instances.
     */
    public function addRoute(Route $route): void;

    /**
     * Add a route.
     *
     * This method creates a `Route` instance with all params given,
     * MUST call `addRoute()` method to populate the collection and returns the `Route`.
     *
     * @param string $path
     * @param callable|string $callback
     * @param null|string $name The name of the route.
     * @param array|null $methods The HTTP methods.
     * @return Route
     */
    public function route(
        string $path,
        callable|string $callback,
        ?string $name = null,
        ?array $methods = null
    ): Route;

    /**
     * Match a request against the known routes.
     *
     * Implementations will aggregate required information from the provided
     * request instance and pass them to the underlying router implementation;
     * when done, they will then marshal a `RouteResult` instance indicating
     * the results of the matching operation and return it to the caller.
     */
    public function match(Request $request): RouteResult;

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any substitutions and attempts to generate a
     * URI from it. Additional router-dependent options may be passed.
     *
     * The URI generated MUST NOT be escaped. If you wish to escape any part of
     * the URI, this should be performed afterward; consider passing the URI
     * to league/uri to encode it.
     *
     * @throws Exception|RouteNotFoundException If unable to generate the given URI.
     */
    public function generateUri(string $name, array $substitutions = [], array $options = []): string;

    /**
     * Retrieve all directly registered routes with the application.
     *
     * @return Route[]
     */
    public function getRoutes(): array;

    /**
     * Retrieve Route by name
     */
    public function getRouteName(string $name): ?Route;
}
