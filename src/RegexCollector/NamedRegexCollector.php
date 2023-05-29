<?php

declare(strict_types=1);

namespace Pg\Router\RegexCollector;

use Pg\Router\Parser\NamedParser;
use Pg\Router\Parser\ParserInterface;
use Pg\Router\Route;

class NamedRegexCollector extends AbstractRegexCollector
{
    protected ?array $data = null;
    private ?ParserInterface $parser;

    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser;
    }

    protected function getParser(): ParserInterface
    {
        if (!$this->parser) {
            $this->parser = new NamedParser();
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

        $regex = $this->getParser()->parse($route->getPath());

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
