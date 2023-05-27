<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Route;

abstract class AbstractRegexCollector implements RegexCollectorInterface
{
    public const ANY_METHODS = 'ANY';

    /**
     * @inheritDoc
     */
    abstract public function addRoute(Route $route): void;

    /**
     * @inheritDoc
     */
    abstract public function getData(): array;
}
