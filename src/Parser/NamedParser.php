<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Regex\Regex;

class NamedParser implements ParserInterface
{
    protected string $regex;
    /** @var array  Default tokens ["tokenName" => "regex"]*/
    protected array $tokens = [];

    public function parse(string $path, array $tokens = []): string|array
    {
        if (empty($path)) {
            return '/';
        }

        if ($path === '/') {
            return '/';
        }

        // Quick check for simple static routes
        if (!$this->containsVariables($path) && !$this->containsOptionalSegments($path)) {
            return $path;
        }

        $this->regex = $path;
        $this->tokens = $tokens;

        $this->parseOptionalParts();
        $this->parseVariableParts();

        return $this->regex;
    }

    /**
     * Check if a path contains variable patterns
     *
     * @param string $path
     * @return bool
     */
    protected function containsVariables(string $path): bool
    {
        return str_contains($path, '{');
    }

    /**
     * Check if a path contains optional segments
     *
     * @param string $path
     * @return bool
     */
    protected function containsOptionalSegments(string $path): bool
    {
        return str_contains($path, '[!');
    }

    /**
     * Split in different pattern route with optional parts.
     *
     * @return void
     */
    protected function parseOptionalParts(): void
    {
        $optionalParts = $this->extractOptionalParts();
        if ($optionalParts) {
            $partsWithoutBrackets = rtrim($optionalParts, ']');
            $optionalParts = '[!' . $optionalParts;
            $parts = explode(';', $partsWithoutBrackets);
            $repl = $this->getRegexOptionalAttributesReplacement($parts);
            $this->regex = str_replace($optionalParts, $repl, $this->regex);
        }
    }

    protected function extractOptionalParts(): ?string
    {
        $parts = preg_split('~' . Regex::OPT_REGEX . '~x', $this->regex);
        return $parts[1] ?? null;
    }

    /**
     *
     * Gets the replacement for optional attributes in the regex.
     *
     * @param array $parts The optional attributes.
     *
     * @return string
     *
     */
    protected function getRegexOptionalAttributesReplacement(array $parts): string
    {
        $head = $this->getRegexOptionalAttributesReplacementHead($parts);
        $tail = $head !== '' ? ')?' : '';
        foreach ($parts as $name) {
            $head .= '(?:' . trim($name);
            $tail .= ')?';
        }

        return $head . $tail;
    }

    /**
     *
     * Gets the leading portion of the optional attributes replacement.
     *
     * @param array $parts The optional attributes.
     *
     * @return string
     *
     */
    protected function getRegexOptionalAttributesReplacementHead(array &$parts): string
    {
        // if the optional set is the first part of the path, make sure there
        // is a leading slash in the replacement before the optional attribute.
        $head = '';
        if (str_starts_with($this->regex, '[!')) {
            $name = array_shift($parts);
            $name = ltrim($name, '/');
            $head = '/(?:' . trim($name);
        }
        return $head;
    }

    /**
     * Generate the regex for all routes needed by the path.
     *
     * @return void
     */
    protected function parseVariableParts(): void
    {
        $vars = [];
        $searchPatterns = [];
        $replacements = [];

        preg_match_all('~' . Regex::REGEX . '\s*~x', $this->regex, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            [$full, $name, $token] = array_pad($match, 3, null);

            if (isset($vars[$name])) {
                throw new DuplicateAttributeException(
                    sprintf(
                        'Cannot use the same attribute twice [%s]',
                        $name
                    )
                );
            }

            $subPattern = $this->getSubpattern($name, $token);
            $searchPatterns[] = $full;
            $replacements[] = $subPattern;
            $vars[$name] = $name;
        }

        // Single str_replace call with arrays for all replacements
        if (!empty($searchPatterns)) {
            $this->regex = str_replace($searchPatterns, $replacements, $this->regex);
        }
    }

    /**
     * Return the subpattern for a token with the attribute name.
     *
     * @param string $name
     * @param string|null $token
     * @return string
     */
    protected function getSubpattern(string $name, ?string $token = null): string
    {
        // is there a custom subpattern for the name?
        if (isset($this->tokens[$name]) && is_string($this->tokens[$name])) {
            // if $token is null use route token
            $token = $token ?: $this->tokens[$name];
        }

        // is there a custom subpattern for the name?
        if ($token) {
            return '(?P<' . $name . '>' . trim($token) . ')';
        }

        // use a default subpattern
        return '(?P<' . $name . '>[^/]+)';
    }
}
