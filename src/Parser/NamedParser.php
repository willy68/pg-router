<?php

declare(strict_types=1);

namespace Pg\Router\Parser;

use Pg\Router\Exception\DuplicateAttributeException;
use Pg\Router\Regex\Regex;

class NamedParser implements ParserInterface
{
    protected string $regex;

    public function parse(string $path): string|array
    {
        $this->regex = $path;

        $this->parseOptionalParts();
        $this->parseVariableParts();

        return $this->regex;
    }

    /**
     * Split in different pattern route with optionals parts.
     *
     * @return void
     */
    protected function parseOptionalParts(): void
    {
        preg_match(Regex::OPT_REGEX, $this->regex, $matches);
        if ($matches) {
            $parts = explode(';', $matches[1]);
            $repl = $this->getRegexOptionalAttributesReplacement($parts);
            $this->regex = str_replace($matches[0], $repl, $this->regex);
        }
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
            $head .= '(?:/' . trim($name);
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
        if (str_starts_with($this->regex, '[/')) {
            $name = array_shift($parts);
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

        preg_match_all(Regex::REGEX, $this->regex, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = $match[1];
            $token = $match[2] ?? null;

            if (isset($vars[$name])) {
                throw new DuplicateAttributeException(
                    sprintf(
                        'Cannot use the same attribute twice [%s]',
                        $name
                    )
                );
            }

            $subpattern = $this->getSubpattern($name, $token);
            $this->regex = str_replace($match[0], $subpattern, $this->regex);
            $vars[$name] = $name;
        }
    }

    /**
     * Return the sub pattern for a token with the attribute name.
     *
     * @param string $name
     * @param string|null $token
     * @return string
     */
    protected function getSubpattern(string $name, ?string $token = null): string
    {
        // is there a custom subpattern for the name?
        if ($token) {
            return '(?P<' . $name . '>' . trim($token) . ')';
        }

        // use a default subpattern
        return '(?P<' . $name . '>[^/]+)';
    }
}
