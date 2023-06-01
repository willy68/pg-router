<?php

declare(strict_types=1);

namespace Pg\Router;

use Pg\Router\DuplicateDetector\DuplicateDetectorInterface;
use Pg\Router\DuplicateDetector\DuplicateMethodMapDetector;
use Pg\Router\Generator\UrlGenerator;
use Pg\Router\Matcher\MarkDataMatcher;
use Pg\Router\Matcher\MatcherInterface;
use Pg\Router\Middlewares\Stack\MiddlewareAwareStackTrait;
use Pg\Router\RegexCollector\MarkRegexCollector;
use Pg\Router\RegexCollector\RegexCollectorInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class Router implements RouterInterface
{
    use MiddlewareAwareStackTrait;
    use RouteCollectionTrait;

    protected ?DuplicateDetectorInterface $detector = null;
    /** @var Route[] */
    protected array $routes = [];
    /** @var callable(array|object): MatcherInterface */
    protected $matcherFactory = null;
    private ?RegexCollectorInterface $regexCollector;

    public function __construct(?RegexCollectorInterface $regexCollector = null, ?callable $matcherFactory = null)
    {
        $this->matcherFactory = $matcherFactory;
        $this->regexCollector = $regexCollector;
    }

    public function route(
        string $path,
        callable|array|string $callback,
        ?string $name = null,
        ?array $methods = null
    ): Route {
        $route = new Route($path, $callback, $name, $methods);
        $this->addRoute($route);

        return $route;
    }

    public function addRoute(Route $route): void
    {
        $this->duplicateRoute($route);
        $this->routes[$route->getName()] = $route;
    }

    protected function duplicateRoute(Route $route)
    {
        $this->getDuplicateDetector()->detectDuplicate($route);
    }

    protected function getDuplicateDetector(): DuplicateDetectorInterface
    {
        if (!$this->detector) {
            $this->detector = new DuplicateMethodMapDetector();
        }

        return $this->detector;
    }

    public function match(Request $request): RouteResult
    {
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        $matcher = $this->getMatcher();

        $route = $matcher->match($uri, $method);

        if ($route) {
            return RouteResult::fromRouteSuccess($this->routes[array_key_first($route)], $matcher->getAttributes());
        }

        $allowedMethods = $matcher->getAllowedMethods();

        return RouteResult::fromRouteFailure(!empty($allowedMethods) ? $allowedMethods : null);
    }

    protected function getMatcher(?array $routes = null): MatcherInterface
    {
        if (!$this->matcherFactory) {
            $this->matcherFactory = $this->getMatcherFactory();
        }

        $factory = $this->matcherFactory;

        if (!$routes) {
            $routes = $this->getParsedData();
        }

        return $factory($routes);
    }

    /**
     * @return callable(array|object): MatcherInterface
     */
    protected function getMatcherFactory(): callable
    {
        return fn ($routes): MatcherInterface => new MarkDataMatcher($routes);
    }

    /** Good place to cache data*/
    protected function getParsedData(): array
    {
        foreach ($this->routes as $route) {
            $this->getRegexCollector()->addRoute($route);
        }

        return $this->regexCollector->getData();
    }

    protected function getRegexCollector(): RegexCollectorInterface
    {
        if ($this->regexCollector) {
            return $this->regexCollector;
        }

        $this->regexCollector = new MarkRegexCollector();

        return $this->regexCollector;
    }

    public function generateUri(string $name, array $substitutions = [], array $options = []): string
    {
        return (new UrlGenerator($this))->generate($name, $substitutions);
    }

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
    public function group(string $prefix, callable $callable): RouteGroup
    {
        $group = new RouteGroup($prefix, $callable, $this);
        /* run group to inject routes on router*/
        $group();

        return $group;
    }

    /**
     * Generate crud Routes
     *
     * @param string $prefixPath
     * @param callable|string $callable
     * @param string $prefixName
     * @return RouteGroup
     */
    public function crud(string $prefixPath, callable|string $callable, string $prefixName): RouteGroup
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
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getRouteName(string $name): ?Route
    {
        return $this->routes[$name] ?? null;
    }
}
