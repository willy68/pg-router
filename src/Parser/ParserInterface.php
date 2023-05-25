<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

interface ParserInterface
{
    /**
     * Parse the path in multiple routes or one route depending on parser system.
     *
     * @param string $path
     * @return string|array
     */
    public function parse(string $path): string|array;
}
