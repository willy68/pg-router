<?php

declare(strict_types=1);

namespace Pg\Router\Exception;

use DomainException;

class DuplicateAttributeException extends DomainException implements
    ExceptionInterface
{
}
