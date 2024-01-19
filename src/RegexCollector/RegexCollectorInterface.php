<?php

namespace Pg\Router\RegexCollector;

use Pg\Router\Route;

interface RegexCollectorInterface
{
    public const ANY_METHODS = 'ANY';

    /**
     * Route to parse
     *
     * @param Route $route
     * @return void
     */
    public function addRoute(Route $route): void;

    /**
     * Get data as an array
     *
     * @return array
     */
    public function getData(): array;
}
