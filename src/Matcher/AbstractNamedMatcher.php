<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

use function array_unique;
use function strtoupper;

abstract class AbstractNamedMatcher implements MatcherInterface
{
    protected array $data;
    protected array $attributes = [];
    protected ?string $matchedRoute;
    protected array $failedRoutesMethod = [];
    protected array $allowedMethods = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function match(string $uri, string $httpMethod): bool|array
    {
        $httpMethod = strtoupper($httpMethod);

        // Try method given
        if (isset($this->data[$httpMethod])) {
            $matches = $this->matchPath($uri, $this->data[$httpMethod]);
            if ($matches) {
                return [$this->matchedRoute => $httpMethod, $this->attributes];
            }
        }

        // Try ANY method
        if (isset($this->data['ANY'])) {
            $matches = $this->matchPath($uri, $this->data['ANY']);
            if ($matches) {
                return [$this->matchedRoute => $httpMethod, $this->attributes];
            }
        }

        // Method not allowed
        foreach ($this->data as $methods => $routeDatas) {
            $matches = $this->matchPath($uri, $routeDatas);

            if (!$matches) {
                continue;
            }

            $name = $this->getMatchedRouteName();

            // Memorize failed route method
            $this->failedRoutesMethod[] = $name;
            $this->allowedMethods[] = $methods;
        }

        return false;
    }

    abstract protected function matchPath(string $uri, array $routeDatas): bool|array;

    abstract protected function foundAttributes(array $matches, ?array $attributesNames = null): array;

    public function getMatchedRouteName(): ?string
    {
        return $this->matchedRoute;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getFailedRoutesMethod(): array
    {
        return $this->failedRoutesMethod;
    }

    public function getAllowedMethods(): array
    {
        return array_unique($this->allowedMethods);
    }
}
