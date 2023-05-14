<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

interface ParserInterface
{
    public function parse(string $path): string|array;
}
