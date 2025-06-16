<?php

namespace Pg\Router\Parser;

class RouteToken
{
    public function __construct(
        public string $token,
        public string $name,
        public ?string $pattern = null
    ) {
    }
}
