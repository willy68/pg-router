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
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException as InvalidCacheArgumentException;

class Router implements RouterInterface
{
    use MiddlewareAwareStackTrait;
    use RouteCollectionTrait;

    public const CONFIG_CACHE_ENABLED = 'cache_enabled';
    public const CONFIG_CACHE_DIR = 'cache_dir';
    public const CONFIG_CACHE_POOL_FACTORY = 'cache_pool_factory';

    private string $cacheDir = '/tmp/cache/router';
    private string $cacheKey = 'router_parsed_data';
    /** @var callable: CachePoolInterface */
    private $cachePoolFactory = null;
    private ?CacheItemPoolInterface $cachePool = null;
    protected ?DuplicateDetectorInterface $detector = null;
    /** @var Route[] */
    protected array $routes = [];
    /** @var callable(array|object): MatcherInterface|null */
    protected $matcherFactory = null;
    private ?RegexCollectorInterface $regexCollector;

    /**
     * $router = new Router(
     *     null,
     *     null,
     *     [
     *          self::CONFIG_CACHE_ENABLED => ($env === 'prod'),
     *          self::CONFIG_CACHE_DIR => '/tmp/cache/router',
     *          self::CONFIG_CACHE_POOL_FACTORY => function (): CacheItemPoolInterface {...}
     *     ]
     * )
     *
     * @param RegexCollectorInterface|null $regexCollector
     * @param callable|null $matcherFactory
     * @param array|null $config
     * @throws CacheException
     */
    public function __construct(
        ?RegexCollectorInterface $regexCollector = null,
        ?callable $matcherFactory = null,
        ?array $config = null
    ) {
        $this->regexCollector = $regexCollector;
        $this->matcherFactory = $matcherFactory;
        $this->loadConfig($config);
    }

    /**
     * Load configuration parameters.
     *
     * @param array|null $config
     * @throws CacheException
     */
    private function loadConfig(?array $config = null): void
    {
        if ($config === null) {
            return;
        }

        $cacheEnabled = (bool)($config[self::CONFIG_CACHE_ENABLED] ?? false);
        $this->cacheDir = (string)($config[self::CONFIG_CACHE_DIR] ?? $this->cacheDir);
        $this->cachePoolFactory = $config[self::CONFIG_CACHE_POOL_FACTORY] ?? $this->getCachePoolFactory();

        if ($cacheEnabled) {
            $this->loadCachePool();
        }
    }

    /**
     * @throws CacheException
     */
    private function loadCachePool(): void
    {
        if ($this->cachePool === null) {
            $cachePoolFactory = $this->cachePoolFactory;
            if (!is_callable($cachePoolFactory)) {
                throw new InvalidCacheArgumentException('Cache pool factory must be a callable.');
            }
            $this->cachePool = $cachePoolFactory();
            if (!$this->cachePool instanceof CacheItemPoolInterface) {
                throw new InvalidCacheArgumentException(
                    'Cache pool factory must return an instance of CacheItemPoolInterface.'
                );
            }
        }
    }

    /**
     * Get the default cache pool factory callable.
     *
     * @return callable (array|object): CacheItemPoolInterface
     */
    protected function getCachePoolFactory(): callable
    {
        return fn(): CacheItemPoolInterface => new PhpFilesAdapter(
            'Router',
            0,
            $this->cacheDir,
            true
        );
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

    protected function duplicateRoute(Route $route): void
    {
        $this->getDuplicateDetector()->detectDuplicate($route);
    }

    protected function getDuplicateDetector(): DuplicateDetectorInterface
    {
        return $this->detector ??= new DuplicateMethodMapDetector();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function match(Request $request): RouteResult
    {
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        $matcher = $this->getMatcher();

        $route = $matcher->match($uri, $method);

        if ($route) {
            return RouteResult::fromRouteSuccess(
                $this->routes[$matcher->getMatchedRouteName()],
                $matcher->getAttributes()
            );
        }

        $allowedMethods = $matcher->getAllowedMethods();

        return RouteResult::fromRouteFailure(!empty($allowedMethods) ? $allowedMethods : null);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getMatcher(?array $routes = null): MatcherInterface
    {
        $this->matcherFactory ??= $this->getMatcherFactory();
        $routes ??= $this->getParsedData();
        return ($this->matcherFactory)($routes);
    }

    /**
     * @return callable(array|object): MatcherInterface
     */
    protected function getMatcherFactory(): callable
    {
        return fn($routes): MatcherInterface => new MarkDataMatcher($routes);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getParsedData(): array
    {
        $cacheItem = null;
        if ($this->cachePool) {
            $cacheItem = $this->cachePool->getItem($this->cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }

        foreach ($this->routes as $route) {
            $this->getRegexCollector()->addRoute($route);
        }

        $data = $this->regexCollector->getData();

        if ($this->cachePool && $cacheItem) {
            $cacheItem->set($data);
            $this->cachePool->save($cacheItem);
        }

        return $data;
    }

    protected function getRegexCollector(): RegexCollectorInterface
    {
        return $this->regexCollector ??= new MarkRegexCollector();
    }

    public function generateUri(string $name, array $substitutions = [], array $options = []): string
    {
        return (new UrlGenerator($this))->generate($name, $substitutions);
    }

    /**
     * Create multiple routes with same prefix.
     */
    public function group(string $prefix, callable $callable): RouteGroup
    {
        $group = new RouteGroup($prefix, $callable, $this);
        $group();
        return $group;
    }

    /**
     * Generate CRUD routes.
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
