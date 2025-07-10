<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

interface ParserInterface
{
    /**
     * Parse the path in multiple routes or one route depending on a parser system.
     *
     * @param string $path
     * @param array $tokens
     * @return string|array
     */
    public function parse(string $path, array $tokens = []): string|array;
}
