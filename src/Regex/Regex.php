<?php

declare(strict_types=1);

namespace Pg\Router\Regex;

class Regex
{
    public const REGEX = '\s*\{\s*([a-zA-Z0-9_][a-zA-Z0-9_-]*)\s*(?::\s*([^{}]*\{*[^{}]*\}*[^{}]*)\s*)?\}';
    public const OPT_REGEX = '\[\!';
}
