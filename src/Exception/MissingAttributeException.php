<?php

declare(strict_types=1);

namespace Pg\Router\Exception;

use DomainException;

class MissingAttributeException extends DomainException implements
    ExceptionInterface
{
}
