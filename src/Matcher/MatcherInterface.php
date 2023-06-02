<?php

declare(strict_types=1);

namespace Pg\Router\Matcher;

interface MatcherInterface
{
    public function match(string $uri, string $httpMethod): bool|array;
    public function getMatchedRouteName(): ?string;
    public function getAttributes(): array;
    public function getFailedRoutesMethod(): array;
    public function getAllowedMethods(): array;
}
