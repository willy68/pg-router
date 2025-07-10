<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Regex\Regex;

use function array_keys;
use function array_pad;
use function explode;
use function preg_match_all;
use function preg_split;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trim;

/**
 * FastMarkParser - High-performance route parser with optimized processing
 *
 * This parser provides an optimized implementation of route parsing with streamlined
 * algorithms and reduced overhead for better performance in route compilation.
 *
 * Features:
 * - Streamlined route variant extraction with minimal regex operations
 * - Optimized optional segment processing without redundant parsing
 * - Direct compilation pipeline for faster route processing
 * - Efficient variable extraction and validation
 * - Memory-optimized data structures for parsed routes
 * - Full compatibility with existing ParserInterface
 *
 * Performance benefits:
 * - Single-pass route analysis for most patterns
 * - Reduced string manipulation operations
 * - Optimized regex compilation and matching
 * - Minimal memory allocation during parsing
 * - Faster processing of complex optional segments
 *
 * Usage example:
 * ```php
 * $parser = new FastMarkParser();
 *
 * // Static route
 * $result = $parser->parse('/blog'); // Returns: [['/blog'], []]
 *
 * // Route with variables
 * $result = $parser->parse('/user/{id:\d+}'); // Returns: [['/user/(\d+)'], ['id']]
 *
 * // Route with optional segments
 * $result = $parser->parse('/archive[/{year:\d+};/{month:\d+}]');
 * // Returns: [['/archive', '/archive/(\d+)', '/archive/(\d+)/(\d+)'], ['year', 'month']]
 * ```
 *
 * @package Pg\Router\Parser
 * @author William Lety
 * @since 1.0.0
 * @see ParserInterface
 */
class FastMarkParser implements ParserInterface
{
    /** @var array  Default tokens ["tokenName" => "regex"]*/
    protected array $tokens = [];

    public function parse(string $path, array $tokens = []): array
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

        $this->tokens = $tokens;

        $routeData = $this->analyzeRoute($path);
        $variants = $this->buildRouteVariants($routeData);
        return $this->compileRoutes($routeData, $variants);
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
     * Analyze route structure and extract components
     *
     * @param string $path
     * @return array
     */
    protected function analyzeRoute(string $path): array
    {
        $pathWithoutClosing = rtrim($path, ']');
        $isOptionalStart = str_starts_with($path, '[!');

        // Split into a base path and optional segments
        $parts = preg_split('~' . Regex::OPT_REGEX . '~x', $pathWithoutClosing);
        $basePath = (trim($parts[0]) ?? '') ?: '/';
        $optionalSegments = $parts[1] ?? '';

        $result = preg_match_all('~' . Regex::REGEX . '~x', $path, $matches, PREG_SET_ORDER);

        return [
            'basePath' => $basePath,
            'optionalSegments' => $optionalSegments,
            'isOptionalStart' => $isOptionalStart,
            'matches' => ($result !== false && $result > 0) ? $matches : [],
        ];
    }

    /**
     * Build all possible route variants from analyzed data
     *
     * @param array $routeData
     * @return array
     */
    protected function buildRouteVariants(array $routeData): array
    {
        $variants = [$routeData['basePath']];

        if (empty($routeData['optionalSegments'])) {
            return $variants;
        }

        $optionalParts = explode(';', $routeData['optionalSegments']);
        $currentPath = $routeData['basePath'];

        // Handle a special case for optional routes starting with '['
        if ($routeData['isOptionalStart'] && $currentPath === '/') {
            $currentPath = '';
        }

        foreach ($optionalParts as $part) {
            $trimmedPart = trim($part);
            $currentPath .= $trimmedPart;
            $variants[] = $currentPath;
        }

        return $variants;
    }

    /**
     * Compile route variants into final regex patterns and extract variables
     *
     * @param array $routeData
     * @param array $variants
     * @return array
     */
    protected function compileRoutes(array $routeData, array $variants): array
    {
        $compiledRoutes = [];
        $allVariables = [];

        foreach ($variants as $route) {
            $routeVariables = [];

            // Extract and replace variables in this route
            $compiledRoute = $this->compileRoute($route, $routeVariables, $routeData['matches']);

            $compiledRoutes[] = $compiledRoute;
            $allVariables = array_merge($allVariables, array_keys($routeVariables));
        }

        // Remove duplicates while preserving order
        $uniqueVariables = array_values(array_unique($allVariables));

        return [$compiledRoutes, $uniqueVariables];
    }

    /**
     * Compile a single route variant
     *
     * @param string $route
     * @param array &$routeVariables
     * @param array $matches
     * @return string
     */
    protected function compileRoute(string $route, array &$routeVariables, array $matches): string
    {
        $searchPatterns = [];
        $replacements = [];

        foreach ($matches as $match) {
            [$full, $name, $token] = array_pad($match, 3, null);

            // Skip if this variable doesn't exist in the current route variant
            if (!str_contains($route, $full)) {
                break;
            }

            // Check for duplicate variables across all variants
            if (isset($routeVariables[$name])) {
                throw new DuplicateAttributeException(
                    sprintf('Cannot use the same attribute twice [%s]', $name)
                );
            }

            $subPattern = $this->buildSubpattern($name, $token);
            $searchPatterns[] = $full;
            $replacements[] = $subPattern;

            $routeVariables[$name] = true;
        }

        // Single str_replace call with arrays for all replacements
        if (!empty($searchPatterns)) {
            $route = str_replace($searchPatterns, $replacements, $route);
        }

        return $route;
    }

    /**
     * Build regex subpattern for a variable
     *
     * @param string $name
     * @param string|null $token
     * @return string
     */
    protected function buildSubpattern(string $name, ?string $token = null): string
    {
        // is there a custom subpattern for the name?
        if (isset($this->tokens[$name])) {
            // if $token is null use route token
            $token = $token ?: $this->tokens[$name];
        }

        return $token ? '(' . trim($token) . ')' : '([^/]+)';
    }
}
