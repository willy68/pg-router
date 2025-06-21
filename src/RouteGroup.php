<?php

declare(strict_types=1);

namespace Pg\Router;

use Pg\Router\Middlewares\MiddlewareAwareStackTrait;

use function ltrim;
use function sprintf;

/**
 * Ex:
 * ```php
 * $router->group('/admin', function (RouteGroup $route) {
 *      $route->route('/acme/route1', 'AcmeController::actionOne', 'route1', ['GET']);
 *      $route->route('/acme/route2', 'AcmeController::actionTwo', 'route2', ['GET'])->setSchemes(['https']);
 *      $route->route('/acme/route3', 'AcmeController::actionThree', 'route3', ['GET']);
 * })
 * ->middleware(Middleware::class);
 * ```
 */
class RouteGroup
{
    use MiddlewareAwareStackTrait;
    use RouteCollectionTrait;

    private string $prefix;
    /** @var callable */
    private $callback;
    private RouteCollectionInterface|RouterInterface $router;

    /**
     * constructor
     *
     * @param string $prefix
     * @param callable $callback
     * @param RouteCollectionInterface|RouterInterface $router
     */
    public function __construct(string $prefix, callable $callback, RouteCollectionInterface|RouterInterface $router)
    {
        $this->prefix = $prefix;
        $this->callback = $callback;
        $this->router = $router;
    }

    /**
     * Run $callable
     */
    public function __invoke(): void
    {
        ($this->callback)($this);
    }

    /**
     * Add a route to match.
     *
     * Accepts a combination of a path and callback, and optionally the HTTP methods allowed.
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
        $path = $path === '/' ? $this->prefix : $this->prefix . sprintf('/%s', ltrim($path, '/'));
        $route = $this->router->route($path, $callback, $name, $methods);
        $route->setParentGroup($this);
        return $route;
    }

    /**
     * Perform all crud routes for a given class controller
     *
     * @param string $callback The class name generally
     * @param string $prefixName
     * @return RouteGroup
     */
    public function crud(string $callback, string $prefixName): self
    {
        $this->get("/", $callback . '::index', "$prefixName.index");
        $this->get("/new", $callback . '::create', "$prefixName.create");
        $this->post("/new", $callback . '::create', "$prefixName.create.post");
        $this->get("/{id:\d+}", $callback . '::edit', "$prefixName.edit");
        $this->post("/{id:\d+}", $callback . '::edit', "$prefixName.edit.post");
        $this->delete("/{id:\d+}", $callback . '::delete', "$prefixName.delete");
        return $this;
    }

    /**
     * Get the value of prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
