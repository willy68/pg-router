<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Route;

abstract class AbstractRegexCollector implements RegexCollectorInterface
{
    /**
     * @inheritDoc
     */
    abstract public function addRoute(Route $route): void;

    /**
     * @inheritDoc
     */
    abstract public function getData(): array;
}
