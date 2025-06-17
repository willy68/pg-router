<?php

declare(strict_types=1);

namespace Pg\Router\DuplicateDetector;

use Pg\Router\Exception\DuplicateRouteException;
use Pg\Router\Route;

use function sprintf;

class DuplicateMethodMapDetector implements DuplicateDetectorInterface
{
    /** @var array<string, string> */
    private array $routes = [];

    /** @var array<string, array<string, string>> */
    private array $pathToMethodMap = [];

    public function detectDuplicate(Route $route): void
    {
        $name = $route->getName();
        $path = $route->getPath();
        $allowedMethods = $route->getAllowedMethods() ?? ['ANY'];

        // Check for duplicate route name
        if (isset($this->routes[$name])) {
            $this->throwDuplicateRoute(sprintf(
                'Route with name `%s` already exists',
                $name
            ));
        }

        // Check for duplicate path/method
        if (isset($this->pathToMethodMap[$path])) {
            $this->checkPathConflicts($path, $allowedMethods);
        }

        $this->memorizeRoute($route);
    }

    private function checkPathConflicts(string $path, array $allowedMethods): void
    {
        $existingMethods = $this->pathToMethodMap[$path];

        // Check for conflicts with existing ANY method
        if (isset($existingMethods['ANY'])) {
            $this->throwConflictError($path, 'ANY', $existingMethods['ANY']);
        }

        // Check for method conflicts
        foreach ($existingMethods as $method => $routeName) {
            if (
                $method === 'ANY' ||
                in_array('ANY', $allowedMethods, true) ||
                in_array($method, $allowedMethods, true)
            ) {
                $this->throwConflictError($path, $method, $routeName);
            }
        }
    }

    private function throwConflictError(string $path, string $method, string $routeName): void
    {
        $this->throwDuplicateRoute(sprintf(
            'Route with path `%s` already exists with method [%s] and name [%s]',
            $path,
            $method,
            $routeName
        ));
    }

    private function memorizeRoute(Route $route): void
    {
        $name = $route->getName();
        $path = $route->getPath();

        $this->routes[$name] = $name;

        $methods = $route->allowsAnyMethod() ? ['ANY'] : $route->getAllowedMethods();

        foreach ($methods as $method) {
            $this->pathToMethodMap[$path][$method] = $name;
        }
    }

    protected function throwDuplicateRoute(string $message)
    {
        throw new DuplicateRouteException($message);
    }
}
