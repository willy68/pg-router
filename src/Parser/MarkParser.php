<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Regex\Regex;

class MarkParser implements ParserInterface
{
    protected string $regex;
    protected array $routes;

    public function parse(string $path): string|array
    {
        $this->regex = $path;
        $this->routes = [];

        $routes = $this->parseOptionalParts();
        $this->parseVariableParts($routes);

        return $this->routes;
    }

    /**
     * Split in different regexes with optionals parts, one route per optional part.
     * The routes[0] is the variable/static part.
     *
     * @return array
     */
    protected function parseOptionalParts(): array
    {
        $routes = $this->getVariablePart();

        preg_match(Regex::OPT_REGEX, $this->regex, $matches);
        if ($matches) {
            $routes = $this->getOptionalParts($routes, $matches);
        }

        return $routes;
    }

    /**
     * Get the first part (variable and/or static) in the array at index 0.
     * If the path start by optional part, the array is empty.
     *
     * @return array
     */
    protected function getVariablePart(): array
    {
        $routes = [];

        $regex = preg_split(Regex::OPT_REGEX, $this->regex);
        if (is_array($regex)) {
            // Put variable part (or static) in first without optional part
            $routes[] = $regex[0] ?: '/';
        }

        return $routes;
    }

    /**
     * Generate all routes for all optional parts.
     *
     * @param array $routes
     * @param array $matches
     * @return array
     */
    protected function getOptionalParts(array $routes, array $matches): array
    {
        $parts = explode(';', $matches[1]);
        $repl = '';

        foreach ($parts as $part) {
            $repl .= '/' . trim($part);
            $routes[] = str_replace($matches[0], $repl, $this->regex);
        }

        return $routes;
    }

    /**
     * Generate the regex for all routes needed by the path.
     *
     * @param array $routes
     * @return void
     */
    protected function parseVariableParts(array $routes): void
    {
        $attributes = [];
        $regex = [];

        foreach ($routes as $route) {
            $vars = [];
            preg_match_all(Regex::REGEX, $route, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $name = $match[1];
                $token = $match[2] ?? null;

                if (isset($vars[$name])) {
                    throw new DuplicateAttributeException(
                        sprintf(
                            'Cannot use the same attribute twice [%s]',
                            $name
                        )
                    );
                }

                $subpattern = $this->getSubpattern($name, $token);
                $route = str_replace($match[0], $subpattern, $route);
                $vars[$name] = $name;
            }
            $attributes = $attributes + $vars;
            $regex[] = $route;
        }

        $this->routes = [$regex, $attributes];
    }

    /**
     * Return the sub pattern for a token with or without the attribute name.
     *
     * @param string $name
     * @param string|null $token
     * @return string
     */
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
