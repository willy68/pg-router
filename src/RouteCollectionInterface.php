<?php

/**
 * https://github.com/thephpleague/route
 */

declare(strict_types=1);

namespace Pg\Router;

interface RouteCollectionInterface
{
    /**
     * Add a route to the collection.
     *
     * Accepts a combination of a path and callback,
     * and optionally the HTTP methods allowed and name.
     *
     * @param string $path
     * @param callable|string $callback
     * @param string|null $name
     * @param array|null $methods
     * @return Route
     */
    public function route(string $path, callable|string $callback, ?string $name = null, ?array $methods = null): Route;

    /**
     * Add a route that responds to GET HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param string|null $name
     * @return Route
     */
    public function get(string $path, callable|string $callable, ?string $name = null): Route;

    /**
     * Add a route that responds to POST HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param string|null $name
     * @return Route
     */
    public function post(string $path, callable|string $callable, ?string $name = null): Route;

    /**
     * Add a route that responds to PUT HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param string|null $name
     * @return Route
     */
    public function put(string $path, callable|string $callable, ?string $name = null): Route;

    /**
     * Add a route that responds to PATCH HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param string|null $name
     * @return Route
     */
    public function patch(string $path, callable|string $callable, ?string $name = null): Route;

    /**
     * Add a route that responds to DELETE HTTP method
     *
     * @param string $path
     * @param callable|string $callable
     * @param string|null $name
     * @return Route
     */
    public function delete(string $path, callable|string $callable, ?string $name = null): Route;

    /**
     * @param string $path
     * @param callable|string $callback
     * @param string|null $name The name of the route.
     * @return Route
     */
    public function any(string $path, callable|string $callback, ?string $name = null): Route;

    /**
     * Add a route that responds to HEAD HTTP method
     *
     * @param string $path
     * @param callable|string $callable
     * @param string|null $name
     * @return Route
     */
    public function head(string $path, callable|string $callable, ?string $name = null): Route;

    /**
     * Add a route that responds to OPTIONS HTTP method
     *
     * @param string $path
     * @param callable|string $callable
     * @param string|null $name
     * @return Route
     */
    public function options(string $path, callable|string $callable, ?string $name = null): Route;

    /**
     * Create multiple routes with same prefix
     *
     * Ex:
     * ```
     * $router->group('/admin', function (RouteGroup $route) {
     *  $route->route('/acme/route1', 'AcmeController::actionOne', 'route1', [GET]);
     *  $route->route('/acme/route2', 'AcmeController::actionTwo', 'route2', [GET])->middleware(Middleware::class);
     *  $route->route('/acme/route3', 'AcmeController::actionThree', 'route3', [GET]);
     * })
     * ->middleware(Middleware::class);
     * ```
     */
    public function group(string $prefix, callable $callable): RouteGroup;

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
