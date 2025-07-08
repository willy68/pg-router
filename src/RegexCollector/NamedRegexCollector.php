<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Parser\NamedParser;
use Pg\Router\Parser\ParserInterface;
use Pg\Router\Route;

class NamedRegexCollector implements RegexCollectorInterface
{
    protected array $routes = [];
    protected ?array $data = null;
    private ?ParserInterface $parser;

    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser;
    }

    /**
     * @inheritDoc
     */
    public function addRoute(Route $route): void
    {
        $this->routes[] = $route;
    }

    public function addRoutes(array $routes): void
    {
        $this->routes = array_merge($this->routes, $routes);
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        $this->parseRoutes($this->routes);
        return $this->data;
    }

    protected function parseRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $methods = $route->getAllowedMethods() ?? [self::ANY_METHODS];
            $name = $route->getName();

            $regex = $this->getParser()->parse($route->getPath());

            foreach ($methods as $method) {
                $this->data[$method][$name] = ['regex' => $regex];
            }
        }
    }

    protected function getParser(): ParserInterface
    {
        if (!$this->parser) {
            $this->parser = new NamedParser();
        }

        return $this->parser;
    }
}
