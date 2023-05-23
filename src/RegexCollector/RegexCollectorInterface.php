<?php

namespace Pg\Router\RegexCollector;

use Pg\Router\Route;

interface RegexCollectorInterface
{
    /**
     * Route to parse
     *
     * @param Route $route
     * @return void
     */
    public function addRoute(Route $route): void;

    /**
     * Get data as an array or null
     *
     * @return array
     */
    public function getData(): array;
}
