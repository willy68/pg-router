<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Regex\Regex;

use function explode;
use function is_array;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function str_replace;

abstract class AbstractParser implements ParserInterface
{
    protected string $regex;
    protected array $routes;

    abstract public function parse(string $path): string|array;

    /**
     * Split in different pattern route with optionals parts
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
     * Get the first part (variable and/or static) in array at index 0
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
     * Generate regex for all routes needed by the path
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

    abstract protected function getSubpattern(string $name, ?string $token = null): string;
}
