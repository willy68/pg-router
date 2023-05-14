<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

use Pg\Router\Parser\NamedParamsParser;
use Pg\Router\Parser\ParserInterface;
use Pg\Router\Route;

use function array_merge;
use function is_string;
use function preg_match;
use function rawurldecode;
use function strtoupper;

class RouteMatcher implements MatcherInterface
{
    protected ?ParserInterface $parser = null;
    /** @var Route[] */
    protected array $routes;
    protected array $attributes = [];
    protected ?Route $matchedRoute;
    protected array $failedRoutesMethod = [];
    protected array $allowedMethods = [];

    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    public function match(string $uri, string $httpMethod): bool|array
    {
        $httpMethod = strtoupper($httpMethod);

        foreach ($this->routes as $route) {
            $path = $route->getPath();
            $matches = $this->matchPath($uri, $path);

            if (!$matches) {
                continue;
            }

            if (!$route->allowsMethod($httpMethod)) {
                // Memorize failed route method
                $this->failedRoutesMethod[] = $route;
                $this->allowedMethods = array_merge($this->allowedMethods, $route->getAllowedMethods());
                continue;
            }

            $this->attributes = $this->foundAttributes($matches);
            $this->matchedRoute = $route;

            return [$route->getName() => $httpMethod, $this->attributes];
        }

        return false;
    }

    protected function matchPath(string $uri, string $path): bool|array
    {
        $parser = $this->getParser();
        $routeVars = $parser->parse($path);
        [$regexes, ] = $routeVars;
        foreach ($regexes as $regex) {
            if (!preg_match('~^' . $regex . '$~', $uri, $matches)) {
                continue;
            }
            return $matches;
        }
        return false;
    }

    protected function getParser(): ParserInterface
    {
        if (null === $this->parser) {
            $this->parser = new NamedParamsParser();
        }
        return $this->parser;
    }

    public function getAllowedMethods(): array
    {
        return array_unique($this->allowedMethods);
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
}
