<?php

declare(strict_types=1);

namespace Pg\Router\Exception;

use InvalidArgumentException as PhpInvalidArgumentException;

/** @final */
class InvalidArgumentException extends PhpInvalidArgumentException implements ExceptionInterface
{
}
