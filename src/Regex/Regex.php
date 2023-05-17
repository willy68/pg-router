<?php

declare(strict_types=1);

namespace Pg\Router\Regex;

class Regex
{
    // From fastRoute
    //public const REGEX = '~{\s*([a-zA-Z_][a-zA-Z0-9_-]*)\s*(?::\s*([^{}]*(?:\{(?-1)\}[^{}]*)*)\s*)?}~';
    // Perso
    public const REGEX = '~\s*\{\s*([a-zA-Z0-9_][a-zA-Z0-9_-]*)\s*(?::\s*([^{}]*{*[^{}]*}*[^{}]*)\s*)?}~';
    // For new format
    //public const OPT_REGEX = '~\s*\[\s*/\s*({\s*[a-zA-Z0-9_][a-zA-Z0-9_-]*\s*:*\s*[^{}]*{*[^{}]*}*[^/]*}*)]~';
    public const OPT_REGEX = '~\s*\[\s*/\s*({\s*[a-zA-Z0-9_][a-zA-Z0-9_-]*\s*(?::\s*[^{}]*{*[^{}]*}*[^/]*)?}*)]~';
}
