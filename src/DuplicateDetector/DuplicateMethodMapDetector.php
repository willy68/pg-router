<?php

declare(strict_types=1);

namespace Pg\Router\DuplicateDetector;

use Pg\Router\Exception\DuplicateRouteException;
use Pg\Router\Route;

use function sprintf;

class DuplicateMethodMapDetector implements DuplicateDetectorInterface
{
    protected array $methodToPathMap = [];
    protected array $routes = [];

    public function detectDuplicate(Route $route): void
    {
        $this->detectDuplicateRoute($route);
        $this->memorizeRoute($route);
    }
    protected function detectDuplicateRoute(Route $route): void
    {
        if (isset($this->routes[$route->getName()])) {
            $this->throwDuplicateRoute(
                sprintf(
                    'Route with name `%s` already exists',
                    $route->getName()
                )
            );
        }

        $path = $route->getPath();

        if (isset($this->methodToPathMap['ANY'][$path])) {
            $this->throwDuplicateRoute(
                sprintf(
                    'Route with path `%s` already exists with method [%s]',
                    $path,
                    'ANY'
                )
            );
        }

        $allowedMethods = $route->getAllowedMethods() ?? ['ANY'];
        foreach ($this->methodToPathMap as $method => $routePath) {
            foreach ($allowedMethods as $allowedMethod) {
                if (
                    isset($this->methodToPathMap[$allowedMethod][$path]) ||
                    ($allowedMethod === 'ANY' && isset($this->methodToPathMap[$method][$path]))
                ) {
                    $this->throwDuplicateRoute(
                        sprintf(
                            'Route with path `%s` already exists with method [%s] and name [%s]',
                            $route->getPath(),
                            $method,
                            $this->methodToPathMap[$method][$path]
                        )
                    );
                }
            }
        }
    }

    protected function memorizeRoute(Route $route): void
    {
        $name = $route->getName();
        $this->routes[$route->getName()] = $name;

        if ($route->allowsAnyMethod()) {
            $this->methodToPathMap['ANY'][$name] = $name;
            return;
        }

        foreach ($route->getAllowedMethods() as $method) {
            $this->methodToPathMap[$method][$route->getPath()] = $name;
        }
    }

    protected function throwDuplicateRoute(string $message)
    {
        throw new DuplicateRouteException($message);
    }
}
