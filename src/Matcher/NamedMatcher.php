<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

class NamedMatcher implements MatcherInterface
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
                $this->attributes = $this->foundAttributes($matches);
                return [$this->matchedRoute => $httpMethod, $this->attributes];
            }
        }

        // Try ANY method
        if (isset($this->data['ANY'])) {
            $matches = $this->matchPath($uri, $this->data['ANY']);
            if ($matches) {
                $this->attributes = $this->foundAttributes($matches);
                return [$this->matchedRoute => $httpMethod, $this->attributes];
            }
        }

        // Method not allowed
        foreach ($this->data as $methods => $regexToRoute) {
            $matches = $this->matchPath($uri, $regexToRoute);

            if (!$matches) {
                continue;
            }

            $name = $this->matchedRoute;

            // Memorize failed route method
            $this->failedRoutesMethod[] = $name;
            $this->allowedMethods[] = $methods;
        }

        return false;
    }

    protected function matchPath(string $uri, array $regexToRoute): bool|array
    {
        foreach ($regexToRoute as $name => $routes) {
            if (!preg_match('~^' . $routes['regex'] . '$~x', $uri, $matches)) {
                continue;
            }

            $this->matchedRoute = $name;

            return $matches;
        }

        return false;
    }

    protected function foundAttributes(array $matches): array
    {
        $attributes = [];
        foreach ($matches as $key => $val) {
            if (is_string($key) && $val !== '') {
                $attributes[$key] = rawurldecode($val);
            }
        }

        return $attributes;
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
