<?php

namespace Pg\Router\Matcher;

use Psr\Http\Message\ServerRequestInterface;

interface RequestMatcherInterface
{
    /**
     * Match a request
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    public function match(ServerRequestInterface $request): bool;
}
