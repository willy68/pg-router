<?php

declare(strict_types=1);

namespace Pg\Router;

use Pg\Router\Exception\ImmutableProperty;
use Pg\Router\Exception\InvalidArgumentException;
use Pg\Router\Middlewares\MiddlewareAwareStackTrait;

use function array_map;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function strcmp;
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
    protected ?string $host = null;
    protected ?int $port = null;
    protected ?array $schemes = null;
    protected $callback;
    protected ?RouteGroup $group = null;
    protected ?string $name = null;
    protected ?array $methods = null;
    /** @var array  Default tokens ["tokenName" => "regex"]*/
    protected array $tokens = [];


    public function __construct(
        string $path,
        callable|array|string $callback,
        ?string $name = null,
        ?array $methods = self::HTTP_METHOD_ANY
    ) {
        $methods = is_string($methods) ? [$methods] : $methods;

        // Place to validate http methods
        $this->methods = is_array($methods) ? $this->validateHttpMethods($methods) : self::HTTP_METHOD_ANY;

        if ($name === null || $name === '') {
            $name = $this->methods === self::HTTP_METHOD_ANY
                ? $path
                : $path . '^' . implode(self::HTTP_METHOD_SEPARATOR, $this->methods);
        }

        $this->name = $name;
        $this->callback = $callback;
        $this->path = $path;
    }

    public function getCallback(): callable|array|string
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

        if (strcmp($prefix, substr($path, 0, strlen($prefix))) === 0) {
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

    public function getAllowedMethods(): ?array
    {
        return $this->methods;
    }

    /**
     * Indicate whether the route allows the specified method.
     *
     * @param string $method HTTP method to test.
     */
    public function allowsMethod(string $method): bool
    {
        $method = strtoupper($method);
        return $this->allowsAnyMethod() || in_array($method, $this->methods ?? [], true);
    }

    /**
     * Indicates whether the route allows any HTTP method.
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
     * Get all schemes array available for this route
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
     * Check if the route allows the specified scheme.
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
     * Checks if the route allows any scheme.
     */
    public function allowsAnyScheme(): bool
    {
        return $this->schemes === self::HTTP_SCHEME_ANY;
    }

    /**
     * Add new tokens, but preserve existing tokens
     * Through this method you can set tokens in an array ["id" => "[0-9]+", "slug" => "[a-zA-Z-]+[a-zA-Z0-9_-]+"]
     * @param array $tokens
     * @return $this
     */
    public function setTokens(array $tokens): self
    {
        $this->tokens = $this->tokens + $tokens;
        return $this;
    }

    /**
     * Override existing tokens and/or add new
     * Through this method you can set tokens in an array ["id" => "[0-9]+", "slug" => "[a-zA-Z0-9_-]+"]
     * @param array $tokens
     * @return $this
     */
    public function updateTokens(array $tokens): self
    {
        $this->tokens = $tokens + $this->tokens;
        return $this;
    }

    /**
     * Get tokens
     *
     * @return array
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    protected function validateHttpMethods(array $methods): array
    {
        if (empty($methods)) {
            throw new InvalidArgumentException('Http methods array is empty');
        }

        // Define allowed HTTP methods
        $validMethods = [
            'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD', 'CONNECT', 'TRACE'
        ];

        // Normalize and validate each method
        $normalized = array_map('strtoupper', $methods);

        foreach ($normalized as $method) {
            if (!in_array($method, $validMethods, true)) {
                throw new InvalidArgumentException(sprintf('Invalid HTTP method: %s', $method));
            }
        }

        return $normalized;
    }
}
