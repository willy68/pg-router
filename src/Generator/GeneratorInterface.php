<?php

namespace Pg\Router\Generator;

use Exception;
use Pg\Router\Exception\RouteNotFoundException;

interface GeneratorInterface
{
    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any substitutions and attempts to generate a
     * URI from it.
     * Variable attributes must be given. Optional attributes could be omitted.
     * Variable and optional attributes must match route regex.
     *
     * @throws Exception|RouteNotFoundException If unable to generate the given URI.
     */
    public function generate(string $name, array $attributes = []): string;
}
