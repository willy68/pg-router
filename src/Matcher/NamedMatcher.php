<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

class NamedMatcher extends AbstractNamedMatcher
{
    protected function matchPath(string $uri, array $routeDatas): bool|array
    {
        foreach ($routeDatas as $name => $routes) {
            if (!preg_match('~^' . $routes['regex'] . '$~x', $uri, $matches)) {
                continue;
            }

            $this->attributes = $this->foundAttributes($matches);
            $this->matchedRoute = $name;

            return $matches;
        }

        return false;
    }

    protected function foundAttributes(array $matches, ?array $attributesNames = null): array
    {
        $attributes = [];
        foreach ($matches as $key => $val) {
            if (is_string($key) && $val !== '') {
                $attributes[$key] = rawurldecode($val);
            }
        }

        return $attributes;
    }
}
