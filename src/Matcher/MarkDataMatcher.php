<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

use function preg_match;
use function rawurldecode;

class MarkDataMatcher extends AbstractNamedMatcher
{
    protected function matchPath(string $uri, array $routeDatas): bool|array
    {
        foreach ($routeDatas as $routes) {
            if (!preg_match($routes['regex'], $uri, $matches)) {
                continue;
            }

            $name = $matches['MARK'];
            $attributesNames = $routes['attributes'][$name];

            $this->matchedRoute = $name;
            $this->attributes = $this->foundAttributes($matches, $attributesNames);

            return $matches;
        }

        return false;
    }

    protected function foundAttributes(array $matches, ?array $attributesNames = null): array
    {
        $attributes = [];

        $i = 1;
        foreach ($attributesNames as $attributeName) {
            if (isset($matches[$i]) && '' !== $matches[$i]) {
                $attributes[$attributeName] = rawurldecode($matches[$i++]);
            }
        }

        return $attributes;
    }
}
