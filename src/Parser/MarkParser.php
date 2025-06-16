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

        $routes = $this->extractRouteVariants();
        $this->compileRoutes($routes);

        return $this->routes;
    }

    /**
     * Extract possible path variants including optional segments.
     */
    protected function extractRouteVariants(): array
    {
        $base = $this->extractBasePath();
        preg_match(Regex::OPT_REGEX, $this->regex, $matches);

        return $matches ? $this->expandOptionalSegments($base, $matches) : $base;
    }

    /**
     * Extract static/variable prefix before optional segments.
     */
    protected function extractBasePath(): array
    {
        $parts = preg_split(Regex::OPT_REGEX, $this->regex);
        return [($parts[0] ?? '') ?: '/'];
    }

    /**
     * Expand a base route into variants using optional parts.
     */
    protected function expandOptionalSegments(array $base, array $matches): array
    {
        $optionalParts = explode(';', $matches[1]);
        $variants = $base;
        $current = '';

        foreach ($optionalParts as $part) {
            $current .= trim($part);
            $variants[] = str_replace($matches[0], $current, $this->regex);
        }

        return $variants;
    }

    /**
     * Compile all route variants into final regex and attribute map.
     */
    protected function compileRoutes(array $routes): void
    {
        $allAttributes = [];
        $compiledRoutes = [];

        foreach ($routes as $route) {
            $attributes = [];
            preg_match_all(Regex::REGEX, $route, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                [$full, $name, $token] = array_pad($match, 3, null);

                if (isset($attributes[$name])) {
                    throw new DuplicateAttributeException(
                        sprintf('Cannot use the same attribute twice [%s]', $name)
                    );
                }

                $subPattern = $this->getSubpattern($name, $token);
                $route = str_replace($full, $subPattern, $route);
                $attributes[$name] = true;
            }

            $allAttributes += array_fill_keys(array_keys($attributes), true);
            $compiledRoutes[] = $route;
        }

        $this->routes = [$compiledRoutes, array_keys($allAttributes)];
    }

    /**
     * Return a subpattern for a route variable.
     */
    protected function getSubpattern(string $name, ?string $token = null): string
    {
        return $token ? '(' . trim($token) . ')' : '([^/]+)';
    }
}
