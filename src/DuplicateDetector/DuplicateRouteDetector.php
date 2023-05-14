<?php

declare(strict_types=1);

namespace Pg\Router\DuplicateDetector;

use Pg\Router\Exception\DuplicateRouteException;
use Pg\Router\Route;

class DuplicateRouteDetector implements DuplicateDetectorInterface
{
    protected array $pathToMethodMap = [];
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

        if (!isset($this->pathToMethodMap[$route->getPath()])) {
            return;
        }

        if ($route->allowsAnyMethod() && isset($this->pathToMethodMap[$route->getPath()])) {
            $this->throwDuplicateRoute(
                sprintf(
                    'Route with path `%s` already exists with method [%s]',
                    $route->getPath(),
                    'ANY'
                )
            );
        }

        $methods = $route->getAllowedMethods();
        foreach ($methods as $method) {
            if (
                isset($this->pathToMethodMap[$route->getPath()][$method]) ||
                isset($this->pathToMethodMap[$route->getPath()]['ANY'])
            ) {
                $this->throwDuplicateRoute(
                    sprintf(
                        'Route with path `%s` already exists with method [%s]',
                        $route->getPath(),
                        $method
                    )
                );
            }
        }
    }

    protected function memorizeRoute(Route $route): void
    {
        $this->routes[$route->getName()] = $route;

        if ($route->allowsAnyMethod()) {
            $this->pathToMethodMap[$route->getPath()]['ANY'] = $route->getName();
            return;
        }

        foreach ($route->getAllowedMethods() as $method) {
            $this->pathToMethodMap[$route->getPath()][$method] = $route->getName();
        }
    }

    protected function throwDuplicateRoute(string $message)
    {
        throw new DuplicateRouteException($message);
    }
}
