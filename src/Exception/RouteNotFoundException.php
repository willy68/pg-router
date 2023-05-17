<?php

declare(strict_types=1);

namespace Pg\Router\Exception;

use DomainException;

class RouteNotFoundException extends DomainException implements
    ExceptionInterface
{
}