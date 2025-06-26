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
        $matches = preg_split('~' . Regex::OPT_REGEX . '~x', $this->url);

        if (false === $matches  || $matches[0] === '/' || $matches[0] === '') {
            return;
        }
        $mainUrl = $matches[0];

        if (preg_match_all('~' . Regex::REGEX . '~x', $mainUrl, $matches, PREG_SET_ORDER) > 0) {
            $routeName = $this->route->getName();

            foreach ($matches as $match) {
                $name = $match[1];
                $token = $match[2] ?? "([^/]+)";

                if (!isset($this->data[$name])) {
                    throw new MissingAttributeException(sprintf(
                        'Parameter value for [%s] is missing for route [%s]',
                        $name,
                        $routeName
                    ));
                }

                $value = (string)$this->data[$name];

                if (!preg_match('~^' . $token . '$~x', $value)) {
                    throw new RuntimeException(sprintf(
                        'Parameter value for [%s] did not match the regex `%s` in route [%s]',
                        $name,
                        $token,
                        $routeName
                    ));
                }

                $this->repl[$match[0]] = rawurlencode($value);
            }
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
        if (!preg_match('~' . Regex::OPT_REGEX . '~x', $this->url, $matches)) {
            return;
        }

        $optionalSegment = $matches[0];
        $optionalParts = explode(';', $matches[1]);
        $replacements = [];

        foreach ($optionalParts as $part) {
            if (preg_match_all('~' . Regex::REGEX . '~x', $part, $exMatches, PREG_SET_ORDER) > 0) {
                $tokenStr = [];
                $names = [];

                foreach ($exMatches as $match) {
                    $tokenStr[] = $match[0];
                    $names[] = isset($match[2]) ? [$match[1], $match[2]] : $match[1];
                }

                $replacement = $this->buildOptionalReplacement($names, $tokenStr, $part);
                if ($replacement !== '') {
                    $replacements[] = $replacement;
                }
            }
        }

        if ($replacements !== []) {
            $this->repl[$optionalSegment] = implode('', $replacements);
        }
    }

    /**
     *
     * Builds the optional replacement for attribute names.
     *
     * @param array $names The optional replacement names.
     * @param array $tokenStr
     * @param string $subject
     * @return string
     */
    protected function buildOptionalReplacement(array $names, array $tokenStr, string $subject): string
    {
        $replacements = [];

        foreach ($names as $name) {
            $token = is_array($name) ? $name[1] : '([^/]+)';
            $paramName = is_array($name) ? $name[0] : $name;

            if (!isset($this->data[$paramName])) {
                return ''; // Options are sequentially optional
            }

            $value = (string)$this->data[$paramName];

            if (!preg_match('~^' . $token . '$~x', $value)) {
                throw new RuntimeException(sprintf(
                    'Parameter value for [%s] did not match the regex `%s`',
                    $paramName,
                    $token
                ));
            }

            $replacements[] = rawurlencode($value);
        }

        return str_replace($tokenStr, $replacements, $subject);
    }
}
