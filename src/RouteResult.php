<?php

declare(strict_types=1);

namespace Pg\Router;

class RouteResult
{
    public const NOT_FOUND = 0;
    public const FOUND = 1;
    public const METHOD_NOT_ALLOWED = 2;

    protected int $status = 0;
    protected ?Route $route = null;
    protected array $matchedAttributes = [];
    protected ?array $allowedMethods = null;

    private function __construct()
    {
    }

    public static function fromRouteSuccess(Route $route, array $attributes = []): self
    {
        $self = new static();
        $self->route = $route;
        $self->matchedAttributes = $attributes;
        $self->status = self::FOUND;
        return $self;
    }

    public static function fromRouteFailure(?array $allowedMethod = null): self
    {
        $self = new static();
        $self->status = $allowedMethod !== null ? self::METHOD_NOT_ALLOWED : self::NOT_FOUND;
        $self->allowedMethods = $allowedMethod;
        return $self;
    }

    /**
     * Retrieve the route that resulted in the route match.
     *
     * @return false|null|Route false if representing a routing failure;
     *     null if not created via fromRoute(); Route instance otherwise.
     */
    public function getMatchedRoute(): bool|Route|null
    {
        return $this->isFailure() ? false : $this->route;
    }

    /**
     * Is this a routing failure result?
     */
    public function isFailure(): bool
    {
        return $this->status === self::NOT_FOUND || $this->status === self::METHOD_NOT_ALLOWED;
    }

    /**
     * Retrieve the matched route name, if possible.
     *
     * If this result represents a failure, return false; otherwise, return the
     * matched route name.
     *
     * @return null|string
     */
    public function getMatchedRouteName(): null|string
    {
        if ($this->isSuccess()) {
            return $this->route?->getName();
        }
        return null;
    }

    /**
     * Does the result represent successful routing?
     */
    public function isSuccess(): bool
    {
        return $this->status === self::FOUND;
    }

    /**
     * Returns the matched params.
     *
     * Guaranteed to return an array, even if it is simply empty.
     */
    public function getMatchedAttributes(): array
    {
        return $this->matchedAttributes;
    }

    /**
     * Does the result represent failure to route due to HTTP method?
     */
    public function isMethodFailure(): bool
    {
        return $this->status === self::METHOD_NOT_ALLOWED;
    }

    /**
     * Retrieve the allowed methods for the route failure.
     *
     * @return null|string[] HTTP methods allowed
     */
    public function getAllowedMethods(): ?array
    {
        return $this->allowedMethods;
    }
}
