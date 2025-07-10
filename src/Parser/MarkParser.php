<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Regex\Regex;

class MarkParser implements ParserInterface
{
    protected string $regex;
    protected array $routes;
    /** @var array  Default tokens ["tokenName" => "regex"]*/
    protected array $tokens = [];

    public function parse(string $path, array $tokens = []): string|array
    {
        if (empty($path)) {
            return [['/'], []];
        }

        if ($path === '/') {
            return [['/'], []];
        }

        // Quick check for simple static routes
        if (!$this->containsVariables($path) && !$this->containsOptionalSegments($path)) {
            return [[$path], []];
        }

        $this->regex = $path;
        $this->routes = [];
        $this->tokens = $tokens;

        $routes = $this->extractRouteVariants();
        $this->compileRoutes($routes);

        return $this->routes;
    }

    /**
     * Check if a path contains variable patterns
     *
     * @param string $path
     * @return bool
     */
    protected function containsVariables(string $path): bool
    {
        return str_contains($path, '{');
    }

    /**
     * Check if a path contains optional segments
     *
     * @param string $path
     * @return bool
     */
    protected function containsOptionalSegments(string $path): bool
    {
        return str_contains($path, '[!');
    }

    /**
     * Extract possible path variants including optional segments.
     */
    protected function extractRouteVariants(): array
    {
        $route = rtrim($this->regex, ']');
        // \[\!\s*(.*[^\s])\s*\]
        $parts = preg_split('~' . Regex::OPT_REGEX . '~', $route);
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
        // is there a custom subpattern for the name?
        if (isset($this->tokens[$name])) {
            // if $token is null use route token
            $token = $token ?: $this->tokens[$name];
        }

        return $token ? '(' . trim($token) . ')' : '([^/]+)';
    }
}
