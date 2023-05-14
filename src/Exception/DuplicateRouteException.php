<?php

declare(strict_types=1);

namespace Pg\Router\Exception;

use DomainException;

/** @final */
class DuplicateRouteException extends DomainException implements
    ExceptionInterface
{
}
