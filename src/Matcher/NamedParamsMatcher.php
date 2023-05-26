<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

class NamedParamsMatcher extends AbstractNamedMatcher
{
    protected function matchPath(string $uri, array $regexToRoute): bool|array
    {
        foreach ($regexToRoute as $name => $routes) {
            foreach ($routes['regex'] as $regex) {
                if (!preg_match('~^' . $regex . '$~x', $uri, $matches)) {
                    continue;
                }

                $this->matchedRoute = $name;

                return $matches;
            }
        }

        return false;
    }
}
