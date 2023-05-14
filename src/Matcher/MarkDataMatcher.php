<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

use function array_unique;
use function preg_match;
use function rawurldecode;
use function strtoupper;

class MarkDataMatcher implements MatcherInterface
{
    protected array $data;
    protected array $attributes = [];
    protected ?string $matchedRoute;
    protected array $failedRoutesMethod = [];
    protected array $allowedMethods = [];

    public function __construct(array $data)
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
        foreach ($this->data as $methods => $regexToRouteVars) {
            $matches = $this->matchPath($uri, $regexToRouteVars);

            if (!$matches) {
                continue;
            }

            $name = $matches['MARK'];

            // Memorize failed route method
            $this->failedRoutesMethod[] = $name;
            $this->allowedMethods[] = $methods;
        }

        return false;
    }

    protected function matchPath(string $uri, array $regexToRouteVars): bool|array
    {
        foreach ($regexToRouteVars as $routeVars) {
            //dd($routeVars);
            if (!preg_match($routeVars['regex'], $uri, $matches)) {
                continue;
            }

            $name = $matches['MARK'];
            $routeVars = $routeVars['routeVars'][$name];
            $varNames = $routeVars['vars'];

            $this->attributes = $this->foundAttributes($matches, $varNames);
            $this->matchedRoute = $name;

            return $matches;
        }
        return false;
    }

    public function getAllowedMethods(): array
    {
        return array_unique($this->allowedMethods);
    }

    protected function foundAttributes(array $matches, array $varNames): array
    {
        $attributes = [];

        for ($i = 1; '' === $matches[$i]; $i++) {
            continue;
        }

        foreach ($varNames as $varName) {
            if (isset($matches[$i]) && '' !== $matches[$i]) {
                $attributes[$varName] = rawurldecode($matches[$i++]);
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
}
