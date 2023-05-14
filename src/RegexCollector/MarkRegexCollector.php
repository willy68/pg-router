<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Parser\DataParser;
use Pg\Router\Parser\ParserInterface;
use Pg\Router\Route;

class MarkRegexCollector implements RegexCollectorInterface
{
    public const ANY_METHODS = 'ANY';

    protected ?array $data = null;
    /** @var callable(): ParserInterface */
    protected $parserFactory;
    private ParserInterface $parser;

    public function __construct(callable $parserFactory = null)
    {
        $this->parserFactory = $parserFactory;
        $this->parser = $this->getParser();
    }

    protected function getParser(): ParserInterface
    {
        if (!$this->parserFactory) {
            $this->parserFactory = $this->getParserFactory();
        }

        $factory = $this->parserFactory;

        return $factory();
    }

    /**
     * @return callable(): ParserInterface
     */
    protected function getParserFactory(): callable
    {
        return fn(): ParserInterface => new DataParser();
    }

    /**
     *
     * @param Route $route
     * @return void
     */
    public function addRoute(Route $route): void
    {
        $methods = $route->getAllowedMethods() ?? [MarkRegexCollector::ANY_METHODS];
        $name = $route->getName();
        $data = $this->parser->parse($route->getPath());
        [$regex, $vars] = $data;

        foreach ($methods as $method) {
            $this->data[$method][$name] = [$regex, $vars, $methods];
        }
    }

    public function getData(): ?array
    {
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
