<?php

declare(strict_types=1);

namespace Pg\Router\DuplicateDetector;

use Pg\Router\Route;

interface DuplicateDetectorInterface
{
    public function detectDuplicate(Route $route): void;
}
