<?php

/**
 * https://github.com/thephpleague/route
 */

declare(strict_types=1);

namespace Pg\Router;

trait RouteCollectionTrait
{
    /**
     * Add a route to the collection
     *
     * @param string $path
     * @param callable|string $callback
     * @param null|string $name The name of the route.
     * @param array|null $methods The HTTP methods.
     * @return Route
     */
    abstract public function route(
        string $path,
        callable|string $callback,
        ?string $name = null,
        ?array $methods = null
    ): Route;

    /**
     * Add a route that responds to GET HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function get(string $path, callable|string $callable, ?string $name = null): Route
    {
        return $this->route($path, $callable, $name, ['GET']);
    }

    /**
     * Add a route that responds to POST HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function post(string $path, callable|string $callable, ?string $name = null): Route
    {
        return $this->route($path, $callable, $name, ['POST']);
    }

    /**
     * Add a route that responds to PUT HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function put(string $path, callable|string $callable, ?string $name = null): Route
    {
        return $this->route($path, $callable, $name, ['PUT']);
    }

    /**
     * Add a route that responds to PATCH HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function patch(string $path, callable|string $callable, ?string $name = null): Route
    {
        return $this->route($path, $callable, $name, ['PATCH']);
    }

    /**
     * Add a route that responds to DELETE HTTP method
     *
     * @param string $path
     * @param callable|string $callable |array|string $callable
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function delete(string $path, callable|string $callable, ?string $name = null): Route
    {
        return $this->route($path, $callable, $name, ['DELETE']);
    }

    /**
     * @param string $path
     * @param callable|string $callback
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function any(string $path, callable|string $callback, ?string $name = null): Route
    {
        return $this->route($path, $callback, $name, null);
    }

    /**
     * Add a route that responds to HEAD HTTP method
     *
     * @param string $path
     * @param callable|string $callable
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function head(string $path, callable|string $callable, ?string $name = null): Route
    {
        return $this->route($path, $callable, $name, ['HEAD']);
    }

    /**
     * Add a route that responds to OPTIONS HTTP method
     *
     * @param string $path
     * @param callable|string $callable
     * @param null|string $name The name of the route.
     * @return Route
     */
    public function options(string $path, callable|string $callable, ?string $name = null): Route
    {
        return $this->route($path, $callable, $name, ['OPTIONS']);
    }
}
