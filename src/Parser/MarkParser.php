<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

class MarkParser extends AbstractParser
{
    public function parse(string $path): string|array
    {
        $this->regex = $path;
        $this->routes = [];

        $routes = $this->parseOptionalParts();
        $this->parseVariableParts($routes);

        return $this->routes;
    }

    protected function getSubpattern(string $name, ?string $token = null): string
    {
        // is there a custom subpattern for the name?
        if ($token) {
            return '(' . trim($token) . ')';
        }

        // use a default subpattern
        return "([^/]+)";
    }
}
