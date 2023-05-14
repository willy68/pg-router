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
            throw new DuplicateRouteException(
                sprintf(
                    'Route with name `%s` already exists',
                    $route->getName()
                )
            );
        }

        $path = $route->getPath();

        if (isset($this->methodToPathMap['ANY'][$path])) {
            throw new DuplicateRouteException(
                sprintf(
                    'Route with path `%s` already exists with method [%s]',
                    $path,
                    'ANY'
                )
            );
        }
        foreach ($this->methodToPathMap as $method => $routePath) {
            $allowedMethods = $route->getAllowedMethods() ?? ['ANY'];
            foreach ($allowedMethods as $allowedMethod) {
                if (
                    isset($this->methodToPathMap[$allowedMethod][$path]) ||
                    ($route->allowsAnyMethod() && isset($this->methodToPathMap[$method][$path]))
                ) {
                    throw new DuplicateRouteException(
                        sprintf(
                            'Route with path `%s` already exists with method [%s] and name [%s]',
                            $route->getPath(),
                            $method,
                            $this->methodToPathMap[$method][$path]->getName()
                        )
                    );
                }
            }
        }
    }

    protected function memorizeRoute(Route $route): void
    {
        $this->routes[$route->getName()] = $route;
        if ($route->allowsAnyMethod()) {
            $this->methodToPathMap['ANY'][$route->getPath()] = $route;
            return;
        }

        foreach ($route->getAllowedMethods() as $method) {
            $this->methodToPathMap[$method][$route->getPath()] = $route;
        }
    }

    protected function throwDuplicateRoute(string $message)
    {
        throw new DuplicateRouteException($message);
    }
}
