<?php

namespace Pg\Router\Generator;

interface GeneratorInterface
{
    public function generate(string $name, array $attributes = []): string;
}
