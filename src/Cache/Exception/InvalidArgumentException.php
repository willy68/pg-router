<?php

declare(strict_types=1);

namespace Pg\Router\Cache\Exception;

use Exception;

class InvalidArgumentException extends Exception implements \Psr\SimpleCache\InvalidArgumentException
{
}
