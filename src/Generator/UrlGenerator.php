<?php

declare(strict_types=1);

namespace Pg\Router\Generator;

use Pg\Router\Route;
use Pg\Router\RouteCollectionInterface;
use Pg\Router\RouterInterface;
use RuntimeException;
use function is_array;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function strtr;

class UrlGenerator implements GeneratorInterface
{
    public const REGEX = '~{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::\s*([^{}]*{*[^{}]*}*[^{}]*)\s*)?}~';
    // Basic
    //public const REGEX = '~{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*:*\s*([^/]*{*[^/]*}*[^/]*)\s*}~';
    //public const OPT_REGEX = '~{\s*/\s*([a-z][a-zA-Z0-9_-]*\s*:*\s*[^/]*{*[^/]*}*[^/]*;*)}~';
    // For new format
    public const OPT_REGEX = '~\[\s*/\s*({[a-z][a-zA-Z0-9_-]*\s*:*\s*[^/]*{*[^/]*}*[^/]*;*}*)\]~';
    //public const EXPLODE_REGEX = '~\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::*\s*([^;]*{*[^;]*,?}*))?~';
    //public const EXPLODE_REGEX = '~\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::\s*([^,]*(?:\{(?-1)\}[^,]*)*))?~';
    // For new format
    public const EXPLODE_REGEX = '~{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::*\s*([^{}]*{*[^{}]*,?}*))?}~';

    protected Route $route;
    protected string $url;
    protected array $data = [];
    protected array $repl = [];
    protected RouterInterface|RouteCollectionInterface $router;


    public function __construct(RouteCollectionInterface|RouterInterface $router)
    {
        $this->router = $router;
    }

    public function generate(string $name, array $attributes = []): string
    {
        $this->route = $this->router->getRouteName($name);
        $this->url = $this->route->getPath();
        $this->data = $attributes;

        $this->buildTokenReplacements();
        $this->buildOptionalReplacements();
        $this->url = strtr($this->url, $this->repl);

        return $this->url;
    }

    /**
     *
     * Builds urlencoded data for token replacements.
     *
     * @return void
     */
    protected function buildTokenReplacements(): void
    {
        // For new format
        $regex = preg_split(self::OPT_REGEX, $this->url);

        if (false === $regex || $regex[0] === '/') {
            return;
        }

        preg_match_all(self::REGEX, $regex[0], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (empty($this->data)) {
                throw new RuntimeException(sprintf(
                    'No replacement attributes found for this route [%s]',
                    $this->route->getName()
                ));
            }

            $name = $match[1];

            // is there data for this variable attribute?
            if (!isset($this->data[$name])) {
                // Variables attributes are not optional
                throw new RuntimeException(sprintf(
                    'Parameter value for [%s] is missing',
                    $name
                ));
            }

            $val = $this->data[$name];
            $token = $match[2] ?? "([^/]+)";

            if (!preg_match('~^' . $token . '$~x', (string)$val)) {
                throw new RuntimeException(sprintf(
                    'Parameter value for [%s] did not match the regex `%s`',
                    $name,
                    $token
                ));
            }
            $this->repl[$match[0]] = $val;
        }
    }

    /**
     *
     * Builds replacements for attributes in the generated path.
     *
     * @return void
     */
    protected function buildOptionalReplacements(): void
    {
        // replacements for optional attributes, if any
        preg_match(self::OPT_REGEX, $this->url, $matches);
        if (!$matches) {
            return;
        }

        // the optional attribute names in the token
        $names = [];
        preg_match_all(self::EXPLODE_REGEX, $matches[1], $exMatches, PREG_SET_ORDER);
        foreach ($exMatches as $match) {
            $name = $match[1];
            $token = $match[2] ?? null;
            $names[] = $token ? [$name, $token] : $name;
        }

        // this is the full token to replace in the path
        $key = $matches[0];

        // build the replacement string
        $this->repl[$key] = $this->buildOptionalReplacement($names);
    }

    /**
     *
     * Builds the optional replacement for attribute names.
     *
     * @param array $names The optional replacement names.
     *
     * @return string
     */
    protected function buildOptionalReplacement(array $names): string
    {
        $repl = '';

        foreach ($names as $name) {
            $token = "([^/]+)";
            if (is_array($name)) {
                $token = $name[1];
                $name = $name[0];
            }

            // is there data for this optional attribute?
            if (!isset($this->data[$name])) {
                // options are *sequentially* optional, so if one is
                // missing, we're done
                return $repl;
            }

            $val = $this->data[$name];

            // Check val matching token
            if (!preg_match('~^' . $token . '$~x', (string)$val)) {
                throw new RuntimeException(sprintf(
                    'Parameter value for [%s] did not match the regex `%s`',
                    $name,
                    $token
                ));
            }

            // encode the optional value
            $repl .= '/' . $val;
        }

        return $repl;
    }
}
