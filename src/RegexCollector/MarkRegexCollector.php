<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Parser\DataParser;
use Pg\Router\Parser\ParserInterface;
use Pg\Router\Route;

/**
 * Compile route into combined regular expression for each allowed methods
 */
class MarkRegexCollector implements RegexCollectorInterface
{
    public const ANY_METHODS = 'ANY';

    protected ?array $data = null;
    private ?ParserInterface $parser = null;

    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser;
    }

    protected function getParser(): ParserInterface
    {
        if (!$this->parser) {
            $this->parser = new DataParser();
        }

        return $this->parser;
    }

    /**
     *
     * @param Route $route
     * @return void
     */
    public function addRoute(Route $route): void
    {
        $methods = $route->getAllowedMethods() ?? [self::ANY_METHODS];
        $name = $route->getName();

        $data = $this->getParser()->parse($route->getPath());
        [$regex, $vars] = $data;

        foreach ($methods as $method) {
            $this->data[$method][$name] = [$regex, $vars, $methods];
        }
    }

    public function getData(): array
    {
        // Good place to cache data
        return $this->computeRegex();
    }

    protected function computeRegex(): array
    {
        $data = [];

        foreach ($this->data as $method => $route) {
            $chunk = array_chunk($route, 15, true);
            $data[$method] = array_map([$this, 'computeRegexData'], $chunk);
        }

        return $data;
    }

    protected function computeRegexData(array $regexToVars): array
    {
        $routeVars = [];
        $regexes = [];

        foreach ($regexToVars as $name => $route) {
            [$routePaths, $vars, $methods] = $route;
            foreach ($routePaths as $path) {
                $regexes[] = $path . '(*MARK:' . $name . ')';
            }
            $routeVars[$name] = ['vars' => $vars, 'methods' => $methods];
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~x';

        return ['regex' => $regex, 'routeVars' => $routeVars];
    }
}
