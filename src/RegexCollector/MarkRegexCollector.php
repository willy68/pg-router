<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Parser\MarkParser;
use Pg\Router\Parser\ParserInterface;
use Pg\Router\Route;

/**
 * Compile route into combined regular expression for each allowed method
 */
class MarkRegexCollector implements RegexCollectorInterface
{
    protected ?array $data = null;
    private ?ParserInterface $parser;
    private int $chunk;

    public function __construct(?ParserInterface $parser = null, int $chunk = 15)
    {
        $this->parser = $parser;
        $this->chunk = $chunk;
    }

    protected function getParser(): ParserInterface
    {
        if (!$this->parser) {
            $this->parser = new MarkParser();
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
            $this->data[$method][$name] = [$regex, $vars];
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
            $chunk = array_chunk($route, $this->chunk, true);
            $data[$method] = array_map([$this, 'computeRegexData'], $chunk);
        }

        return $data;
    }

    protected function computeRegexData(array $routeDatas): array
    {
        $attributes = [];
        $regexes = [];

        foreach ($routeDatas as $name => $route) {
            [$routePaths, $vars] = $route;
            foreach ($routePaths as $path) {
                $regexes[] = $path . '(*MARK:' . $name . ')';
            }
            $attributes[$name] = $vars;
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~x';

        return ['regex' => $regex, 'attributes' => $attributes];
    }
}
