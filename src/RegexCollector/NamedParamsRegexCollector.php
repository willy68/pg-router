<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Parser\NamedParamsParser;
use Pg\Router\Parser\ParserInterface;
use Pg\Router\Route;

class NamedParamsRegexCollector extends AbstractRegexCollector
{
    protected ?array $data = null;
    private ?ParserInterface $parser = null;

    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser;
    }

    protected function getParser(): ParserInterface
    {
        if (!$this->parser) {
            $this->parser = new NamedParamsParser();
        }

        return $this->parser;
    }

    /**
     * @inheritDoc
     */
    public function addRoute(Route $route): void
    {
        $methods = $route->getAllowedMethods() ?? [self::ANY_METHODS];
        $name = $route->getName();

        $data = $this->getParser()->parse($route->getPath());
        [$regex, ] = $data;

        foreach ($methods as $method) {
            $this->data[$method][$name] = ['regex' => $regex];
        }
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        return $this->data;
    }
}
