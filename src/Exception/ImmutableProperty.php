<?php

declare(strict_types=1);

namespace Pg\Router\Exception;

use DomainException;

class ImmutableProperty extends DomainException implements
    ExceptionInterface
{
}
