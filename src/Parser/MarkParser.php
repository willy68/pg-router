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
        $route = rtrim($this->regex, ']');
        // \[\!\s*(.*[^\s])\s*\]
        $parts = preg_split('~' . Regex::REGEX . '(*SKIP)(?!)|\[~x', $route);
        $base = [(trim($parts[0]) ?? '') ?: '/'];
        $optionalParts = explode(';', $parts[1] ?? '');

        return !empty($optionalParts[0]) ? $this->expandOptionalSegments($base, $optionalParts) : $base;
    }

    /**
     * Expand a base route into variants using optional parts.
     */
    protected function expandOptionalSegments(array $base, array $parts): array
    {
        $variants = $base;
        $current = $base[0];

        if ($current === '/' && str_starts_with($parts[0], '/')) {
            $current = '';
        }

        foreach ($parts as $part) {
            $current .= trim($part);
            $variants[] = $current;
        }

        return $variants;
    }

    /**
     * Compile all route variants into final regex and attribute map.
     */
    protected function compileRoutes(array $routes): void
    {
        $allAttributes = [];
        $searchPatterns = [];
        $replacements = [];

        $result = preg_match_all('~' . Regex::REGEX . '~x', $this->regex, $matches, PREG_SET_ORDER);
        $matches = ($result !== false && $result > 0) ? $matches : [];

        foreach ($routes as $route) {
            $attributes = [];

            foreach ($matches as $match) {
                [$full, $name, $token] = array_pad($match, 3, null);

                // Skip if this variable doesn't exist in the current route variant
                if (!str_contains($route, $full)) {
                    break;
                }

                if (isset($attributes[$name])) {
                    throw new DuplicateAttributeException(
                        sprintf('Cannot use the same attribute twice [%s]', $name)
                    );
                }

                $subPattern = $this->getSubpattern($name, $token);
                $searchPatterns[] = $full;
                $replacements[] = $subPattern;
                $attributes[$name] = true;
            }

            $allAttributes += $attributes;
        }

        // Single str_replace call with arrays for all replacements
        if (!empty($searchPatterns)) {
            $routes = str_replace($searchPatterns, $replacements, $routes);
        }

        $this->routes = [$routes, array_keys($allAttributes)];
    }

    /**
     * Return a subpattern for a route variable.
     */
    protected function getSubpattern(string $name, ?string $token = null): string
    {
        return $token ? '(' . trim($token) . ')' : '([^/]+)';
    }
}
