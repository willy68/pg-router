<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

use function explode;
use function is_array;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function str_replace;

abstract class AbstractParser implements ParserInterface
{
    public const REGEX = '~{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::\s*([^{}]*(?:\{(?-1)\}[^{}]*)*)\s*)?}~';
    //public const REGEX = '~{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::\s*([^{}]*{*[^{}]*}*[^{}]*)\s*)?}~';
    public const OPT_REGEX = '~{\s*/\s*([a-z][a-zA-Z0-9_-]*\s*:*\s*[^/]*{*[^/]*}*[^/]*;*)}~';
    // For new format
    //public const OPT_REGEX = '~{\s*/\s*({[a-z][a-zA-Z0-9_-]*\s*:*\s*[^/]*{*[^/]*}*[^/]*;*}*)}~';
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
        $routes = [];

        $regex = preg_split(self::OPT_REGEX, $this->regex);
        if (is_array($regex)) {
            // Put variable part (or static) in first without optional part
            $routes[] = $regex[0];
        }

        preg_match(self::OPT_REGEX, $this->regex, $matches);
        if ($matches) {
            $parts = explode(';', $matches[1]);
            $repl = '';

            foreach ($parts as $part) {
                $repl .= '/' . '{' . $part . '}';
                // For new format
                //$repl .= '/' . $part;
                $routes[] = str_replace($matches[0], $repl, $this->regex);
            }
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
        $vars = [];
        $regex = [];

        foreach ($routes as $route) {
            preg_match_all(self::REGEX, $route, $matches, PREG_SET_ORDER);
            foreach ($matches as $index => $match) {
                $name = $match[1];
                $token = $match[2] ?? null;

                $subpattern = $this->getSubpattern($name, $token);
                $route = str_replace($match[0], $subpattern, $route);
                $vars[$index] = $name;
            }
            $regex[] = $route;
        }

        $this->routes = [$regex, $vars];
    }

    abstract protected function getSubpattern(string $name, ?string $token = null): string;
}
