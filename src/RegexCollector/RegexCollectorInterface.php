<?php

namespace Pg\Router\RegexCollector;

use Pg\Router\Route;

interface RegexCollectorInterface
{
    /**
     * Get route data as an array
     *      [routeName => ['GET','POST'],parsedRouteRegex]
     *
     * @param Route $route
     * @return void
     */
    public function addRoute(Route $route): void;

    public function getData(): ?array;
}
