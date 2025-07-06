<?php

/**
 * @see https://github.com/mezzio/mezzio-router for the canonical source repository
 */

declare(strict_types=1);

namespace Pg\Router;

/**
 * Aggregate routes for the router.
 *
 * This class provides * methods for creating path+HTTP method-based routes and
 * injecting them into the router:
 *
 * - get
 * - post
 * - put
 * - patch
 * - delete
 * - head
 * - options
 * - any
 *
 * A general `route()` method allows specifying multiple request methods and/or
 * arbitrary request methods when creating a path-based route.
 *
 * A general `addRoute()` method with created Route add route on the router.
 *
 * Internally, the class performs some checks for duplicate routes when
 * attaching via one of the exposed methods and will raise an exception when a
 * collision occurs.
 */
class RouteCollector implements RouteCollectionInterface
{
    use RouteCollectionTrait;

    protected RouterInterface $router;
    private array $routes = [];

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Add a route to match.
     *
     * Accepts a combination of a path and callback, and optionally the allowed HTTP methods.
     *
     * @param string $path
     * @param callable|array|string $callback
     * @param null|string $name The name of the route.
     * @param null|array $methods HTTP method to accept; null indicates any.
     * @return Route
     */
    public function route(
        string $path,
        callable|array|string $callback,
        ?string $name = null,
        ?array $methods = null
    ): Route {
        $route = new Route($path, $callback, $name, $methods);
        $this->routes[$route->getName()] = $route;
        return $this->router->addRoute($route);
    }

    /**
     * Create multiple routes with the same prefix
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
    public function group(string $prefix, callable $callable): RouteGroup
    {
        $group = new RouteGroup($prefix, $callable, $this);
        /* run the group to inject routes on router*/
        $group();

        return $group;
    }

    /**
     * Generate crud Routes
     *
     * @param string $prefixPath
     * @param string $callable
     * @param string $prefixName
     * @return RouteGroup
     */
    public function crud(string $prefixPath, string $callable, string $prefixName): RouteGroup
    {
        $group = new RouteGroup(
            $prefixPath,
            function (RouteGroup $route) use ($callable, $prefixName) {
                $route->crud($callable, $prefixName);
            },
            $this
        );
        $group();
        return $group;
    }

    /**
     * Retrieve all directly registered routes with the application.
     *
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Retrieve Route by name
     */
    public function getRouteName(string $name): ?Route
    {
        return $this->routes[$name] ?? null;
    }
}
