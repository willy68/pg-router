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

use function is_array;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function strtr;

class UrlGenerator implements GeneratorInterface
{
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
        $route = $this->router->getRouteName($name);

        if (null === $route) {
            throw new RouteNotFoundException(
                sprintf('Route with name [%s] not found', $name)
            );
        }

        $this->route = $route;
        $this->url = $this->route->getPath();
        $this->data = $attributes;

        if (str_starts_with($this->url, '[/') && empty($this->data)) {
            return '/';
        }

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
        $regex = preg_split(Regex::OPT_REGEX, $this->url);

        if (false === $regex  || $regex[0] === '/' || $regex[0] === '') {
            return;
        }

        preg_match_all(Regex::REGEX, $regex[0], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (empty($this->data)) {
                throw new MissingAttributeException(sprintf(
                    'No replacement attributes found for this route [%s]',
                    $this->route->getName()
                ));
            }

            $name = $match[1];

            // is there data for this variable attribute?
            if (!isset($this->data[$name])) {
                // Variables attributes are not optional
                throw new MissingAttributeException(sprintf(
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
            $this->repl[$match[0]] = rawurlencode((string)$val);
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
        preg_match(Regex::OPT_REGEX, $this->url, $matches);
        if (!$matches) {
            return;
        }

        $optionalParts = explode(';', $matches[1]);

        // the optional attribute names in the token
        $tokenStr = '';
        $replacements = '';
        foreach ($optionalParts as $part) {
            $names = [];
            preg_match_all(Regex::REGEX, $part, $exMatches, PREG_SET_ORDER);
            foreach ($exMatches as $match) {
                $tokenStr = $match[0];
                $name = $match[1];
                $token = $match[2] ?? null;
                $names[] = $token ? [$name, $token] : $name;
            }

            // this is the full token to replace in the path
            $key = $matches[0];

            // build the replacement string
            $replacements .= $this->buildOptionalReplacement($names, $tokenStr, $part);
        }
        $this->repl[$matches[0]] = $replacements;
    }

    /**
     *
     * Builds the optional replacement for attribute names.
     *
     * @param array $names The optional replacement names.
     * @param string $tokenStr
     * @param string $subject
     * @return string
     */
    protected function buildOptionalReplacement(array $names, string $tokenStr, string $subject): string
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
            $repl .= str_replace($tokenStr, rawurlencode((string)$val), $subject);
        }
        return $repl;
    }
}
