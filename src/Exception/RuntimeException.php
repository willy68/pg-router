<?php

declare(strict_types=1);

namespace Pg\Router\Exception;

use RuntimeException as PhpRuntimeException;

class RuntimeException extends PhpRuntimeException implements ExceptionInterface
{
}
