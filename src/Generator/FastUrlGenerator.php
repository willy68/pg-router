<?php

declare(strict_types=1);

namespace Pg\Router\Generator;

use Pg\Router\Exception\MissingAttributeException;
use Pg\Router\Exception\RouteNotFoundException;
use Pg\Router\Exception\RuntimeException;
use Pg\Router\Regex\Regex;
use Pg\Router\Route;
use Pg\Router\RouteCollectionInterface;
use Pg\Router\RouterInterface;

use function preg_match;
use function preg_match_all;
use function preg_split;
use function rawurlencode;
use function sprintf;
use function str_starts_with;
use function strtr;
use function trim;

/**
 * FastUrlGenerator - High-performance URL generator with intelligent caching
 *
 * This generator provides an optimized implementation of URL generation with built-in
 * route analysis caching to improve performance on repeated URL generation calls.
 *
 * Features:
 * - Intelligent route parsing cache to avoid re-analysis
 * - Support for static routes, variables with regex tokens, and optional segments
 * - Comprehensive validation of route parameters against defined patterns
 * - Sequential optional segment processing for complex route structures
 * - Memory-efficient caching system for parsed route data
 * - Full compatibility with existing GeneratorInterface
 *
 * Performance benefits:
 * - First generation: Parses and caches route structure
 * - Subsequent generations: Uses cached data for faster processing
 * - Reduced regex operations and string parsing overhead
 * - Optimized for high-traffic applications
 *
 * Usage example:
 * ```php
 * $generator = new FastUrlGenerator($router);
 *
 * // Static route
 * $url = $generator->generate('home'); // Returns: '/'
 *
 * // Route with variables
 * $url = $generator->generate('user_profile', ['id' => 123]); // Returns: '/user/123'
 *
 * // Route with optional segments
 * $url = $generator->generate('archive', ['year' => '2024', 'month' => '12']);
 * // Returns: '/archive/2024/12'
 * ```
 *
 * @package Pg\Router\Generator
 * @author William Lety
 * @since 1.0.0
 * @see GeneratorInterface
 */
class FastUrlGenerator implements GeneratorInterface
{
    protected RouteCollectionInterface|RouterInterface $router;
    protected array $routeCache = [];

    public function __construct(RouteCollectionInterface|RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Generates a URL from route name and attributes
     *
     * @param string $name Route name
     * @param array $attributes Attributes to replace variables
     * @return string Generated URL
     * @throws RouteNotFoundException
     * @throws MissingAttributeException
     * @throws RuntimeException
     */
    public function generate(string $name, array $attributes = []): string
    {
        $route = $this->getRoute($name);
        $path = $route->getPath();

        // Cache already analyzed routes to optimize performance
        if (!isset($this->routeCache[$name])) {
            $this->routeCache[$name] = $this->parseRoutePath($path);
        }

        $routeData = $this->routeCache[$name];

        return $this->buildUrl($routeData, $attributes, $name);
    }

    /**
     * Retrieves route by name
     *
     * @param string $name
     * @return Route
     * @throws RouteNotFoundException
     */
    protected function getRoute(string $name): Route
    {
        $route = $this->router->getRouteName($name);

        if (null === $route) {
            throw new RouteNotFoundException(
                sprintf('Route with name [%s] not found', $name)
            );
        }

        return $route;
    }

    /**
     * Analyzes route path and extracts structure
     *
     * @param string $path
     * @return array
     */
    protected function parseRoutePath(string $path): array
    {
        $isOptionalStart = str_starts_with($path, '[');
        $pathWithoutClosing = rtrim($path, ']');

        // Separate static part from optional segments
        $parts = preg_split('~' . Regex::REGEX . '(*SKIP)(*F)|\[~x', $pathWithoutClosing);
        $basePath = (trim($parts[0]) ?? '') ?: '/';
        $optionalSegments = $parts[1] ?? '';

        return [
            'basePath' => $basePath,
            'optionalSegments' => $optionalSegments,
            'isOptionalStart' => $isOptionalStart,
            'baseVariables' => $this->extractVariables($basePath),
            'optionalVariables' => $optionalSegments ? $this->parseOptionalSegments($optionalSegments) : []
        ];
    }

    /**
     * Extracts variables from a path segment
     *
     * @param string $segment
     * @return array
     */
    protected function extractVariables(string $segment): array
    {
        $variables = [];

        if (preg_match_all('~' . Regex::REGEX . '~x', $segment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $variables[] = [
                    'full' => $match[0],
                    'name' => $match[1],
                    'token' => $match[2] ?? '([^/]+)',
                ];
            }
        }

        return $variables;
    }

    /**
     * Parses optional segments
     *
     * @param string $segments
     * @return array
     */
    protected function parseOptionalSegments(string $segments): array
    {
        $optionalParts = explode(';', $segments);
        $parsedSegments = [];

        foreach ($optionalParts as $index => $part) {
            $trimmedPart = trim($part);
            $parsedSegments[$index] = [
                'segment' => $trimmedPart,
                'variables' => $this->extractVariables($trimmedPart)
            ];
        }

        return $parsedSegments;
    }

    /**
     * Builds the final URL
     *
     * @param array $routeData
     * @param array $attributes
     * @param string $routeName
     * @return string
     */
    protected function buildUrl(array $routeData, array $attributes, string $routeName): string
    {
        // Special case: optional route at start without attributes
        if ($routeData['isOptionalStart'] && empty($attributes)) {
            return '/';
        }

        $url = $routeData['basePath'];

        // Replace variables in static part
        if ($routeData['basePath'] !== '/') {
            $url = $this->replaceVariables($url, $routeData['baseVariables'], $attributes, $routeName);
        }

        // Process optional segments
        if (!empty($routeData['optionalVariables'])) {
            $optionalUrl = $this->buildOptionalSegments($routeData, $attributes, $routeName);
            $url .= $optionalUrl;
        }

        return $url;
    }

    /**
     * Replaces variables in a segment
     *
     * @param string $segment
     * @param array $variables
     * @param array $attributes
     * @param string $routeName
     * @return string
     */
    protected function replaceVariables(string $segment, array $variables, array $attributes, string $routeName): string
    {
        $replacements = [];

        foreach ($variables as $variable) {
            $name = $variable['name'];
            $token = $variable['token'];

            if (!isset($attributes[$name])) {
                throw new MissingAttributeException(sprintf(
                    'Parameter value for [%s] is missing for route [%s]',
                    $name,
                    $routeName
                ));
            }

            $value = (string) $attributes[$name];

            // Token validation
            if (!preg_match('~^' . $token . '$~x', $value)) {
                throw new RuntimeException(sprintf(
                    'Parameter value for [%s] did not match the regex `%s` in route [%s]',
                    $name,
                    $token,
                    $routeName
                ));
            }

            $replacements[$variable['full']] = rawurlencode($value);
        }

        return strtr($segment, $replacements);
    }

    /**
     * Builds optional segments
     *
     * @param array $routeData
     * @param array $attributes
     * @param string $routeName
     * @return string
     */
    protected function buildOptionalSegments(array $routeData, array $attributes, string $routeName): string
    {
        $optionalUrl = '';
        $segments = $routeData['optionalVariables'];

        foreach ($segments as $index => $segmentData) {
            $segment = $segmentData['segment'];
            $variables = $segmentData['variables'];

            // Check if all required parameters are present
            $canBuild = true;
            foreach ($variables as $variable) {
                if (!isset($attributes[$variable['name']])) {
                    $canBuild = false;
                    break; // Optional segments are sequential
                }
            }

            if (!$canBuild) {
                break;
            }

            // Adjust prefix for first optional segment if necessary
            if ($index === 0 && $routeData['isOptionalStart'] && str_starts_with(ltrim($segment), '/')) {
                $segment = ltrim($segment, '/');
            }

            $builtSegment = $this->replaceVariables($segment, $variables, $attributes, $routeName);
            $optionalUrl .= $builtSegment;
        }

        return $optionalUrl;
    }

    /**
     * Clears route cache (useful for tests or development)
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->routeCache = [];
    }

    /**
     * Returns cache information for a route (useful for debugging)
     *
     * @param string $name
     * @return array|null
     */
    public function getRouteCache(string $name): ?array
    {
        return $this->routeCache[$name] ?? null;
    }
}
