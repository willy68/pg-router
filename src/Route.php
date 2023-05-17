<?php

declare(strict_types=1);

namespace Pg\Router;

use Pg\Router\Middlewares\Stack\MiddlewareAwareStackTrait;

use function array_map;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;

class Route
{
    use MiddlewareAwareStackTrait;

    public const HTTP_METHOD_ANY = null;
    public const HTTP_METHOD_SEPARATOR = ':';
    public const HTTP_SCHEME_ANY = null;

    protected string $path;
    protected ?array $method;
    protected ?string $host;
    protected ?int $port;
    protected ?array $schemes;
    protected $callback;
    protected ?RouteGroup $group = null;
    protected ?string $name = null;
    protected ?array $methods = null;


    public function __construct(
        string $path,
        callable|string $callback,
        ?string $name = null,
        ?array $methods = self::HTTP_METHOD_ANY
    ) {
        $methods = is_string($methods) ? [$methods] : $methods;
        $this->methods = is_array($methods) ? array_map('strtoupper', $methods) : self::HTTP_METHOD_ANY;
        if ($name === null || $name === '') {
            $name = $this->methods === self::HTTP_METHOD_ANY
                ? $path
                : $path . '^' . implode(self::HTTP_METHOD_SEPARATOR, $this->methods);
        }
        $this->name = $name;
        $this->callback = $callback;
        $this->path = $path;
    }

    public function getCallback(): callable|string
    {
        return $this->callback;
    }

    /**
     * Get the parent group
     */
    public function getParentGroup(): ?RouteGroup
    {
        return $this->group;
    }

    /**
     * Set the parent group
     *
     * @param RouteGroup $group
     * @return Route
     */
    public function setParentGroup(RouteGroup $group): self
    {
        $prefix = $group->getPrefix();
        $path = $this->getPath();

        if (\strcmp($prefix, substr($path, 0, strlen($prefix))) === 0) {
            $this->group = $group;
        }

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the route name.
     *
     * @param non-empty-string $name
     * @return Route
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAllowedMethods(): ?array
    {
        return $this->methods;
    }

    /**
     * Indicate whether the specified method is allowed by the route.
     *
     * @param string $method HTTP method to test.
     */
    public function allowsMethod(string $method): bool
    {
        $method = strtoupper($method);
        return $this->allowsAnyMethod() || in_array($method, $this->methods ?? [], true);
    }

    /**
     * Indicate whether any method is allowed by the route.
     */
    public function allowsAnyMethod(): bool
    {
        return $this->methods === self::HTTP_METHOD_ANY;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Get schemes array available for this route
     *
     * @return null|string[] Returns HTTP_SCHEME_ANY or string of allowed schemes.
     */
    public function getSchemes(): ?array
    {
        return $this->schemes;
    }

    /**
     * Set schemes available for this route
     *
     * @param array|null $schemes
     * @return Route
     */
    public function setSchemes(?array $schemes = null): self
    {
        $schemes = is_array($schemes) ? array_map('strtolower', $schemes) : $schemes;
        $this->schemes = $schemes;
        return $this;
    }

    /**
     * Indicate whether the specified scheme is allowed by the route.
     *
     * @param string $scheme
     * @return bool
     */
    public function allowsScheme(string $scheme): bool
    {
        $schemes = strtolower($scheme);
        return $this->allowsAnyScheme() || in_array($schemes, $this->schemes, true);
    }

    /**
     * Indicate whether any schemes is allowed by the route.
     */
    public function allowsAnyScheme(): bool
    {
        return $this->schemes === self::HTTP_SCHEME_ANY;
    }
}