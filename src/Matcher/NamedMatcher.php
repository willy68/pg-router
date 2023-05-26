<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

class NamedMatcher extends AbstractNamedMatcher
{
    protected function matchPath(string $uri, array $regexToRoute): bool|array
    {
        foreach ($regexToRoute as $name => $routes) {
            if (!preg_match('~^' . $routes['regex'] . '$~x', $uri, $matches)) {
                continue;
            }

            $this->matchedRoute = $name;

            return $matches;
        }

        return false;
    }
}
