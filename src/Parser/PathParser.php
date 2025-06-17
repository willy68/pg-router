<?php

namespace Pg\Router\Parser;

use InvalidArgumentException;
use Pg\Router\Exception\DuplicateAttributeException;

class PathParser implements ParserInterface
{
    ///foo/(?P<slug>[a-z]+)(?:/(?P<bar>[0-9]+)(?:/(?P<baz>\d+))?)?

    private string $path;

    /**
     * Analyse un chemin avec paramètres et groupes optionnels.
     *
     * @param string $path
     * @return string|array Liste des variantes de route (tokenisées)
     */
    public function parse(string $path): string|array
    {
        $this->path = $path;
        $parts = $this->tokenize($this->path);
        $this->path = $this->expandOptionals($parts);
        $tokens = $this->parsePath($this->path);
        return $this->parseVariableParts($tokens);
    }

    /**
     * Tokenise la chaîne brute en segments/littéraux et groupes optionnels.
     *
     * @param string $path
     * @return array
     */
    private function tokenize(string $path): array
    {
        $tokens = [];
        $inParam = false;
        $buffer = '';
        $length = strlen($path);
        $bracketCount = 0;

        for ($i = 0; $i < $length; ++$i) {
            $char = $path[$i];

            // Handle {} parameters
            if ($char === '{' && !$inParam) {
                $inParam = true;
                $bracketCount = 1;
                $buffer .= $char;
            } elseif ($char === '{' && $inParam) {
                // Another opening bracket within a parameter (for regular expressions)
                $bracketCount++;
                $buffer .= $char;
            } elseif ($char === '}' && $inParam) {
                $bracketCount--;
                $buffer .= $char;
                if ($bracketCount === 0) {
                    $inParam = false;
                }
            } elseif ($char === '[' && !$inParam) { // Handle optional groups []
                if ($buffer !== '') {
                    $tokens[] = $buffer;
                }
                $tokens[] = substr($path, $i);
                // End of the string
                return $tokens;
            } else {
                    $buffer .= $char;
            }
        }

        if ($inParam) {
            throw new InvalidArgumentException('Unclosed parameter');
        }

        return $tokens;
    }

    /**
     * Développe récursivement les groupes optionnels en toutes les variantes possibles.
     *
     * @param array $tokens
     * @return string
     */
    private function expandOptionals(array $tokens): string
    {
        $path = $this->path;

        foreach ($tokens as $token) {
            if ($token[0] === '[' && str_ends_with($token, ']')) {
                // Optional group with sequential parts separated by a ';'
                $inner = substr($token, 1, -1);
                $parts = explode(';', $inner);
                $repl = $this->getRegexOptionalAttributesReplacement($parts);
                $path = str_replace($token, $repl, $this->path);
            }
        }
        return $path;
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
        if (str_starts_with($this->path, '[/')) {
            $name = array_shift($parts);
            $name = ltrim($name, '/');
            $head = '/(?:' . trim($name);
        }
        return $head;
    }

    private function parsePath(string $path): array
    {
        $tokens = [];
        $length = strlen($path);
        $i = 0;

        while ($i < $length) {
            if ($path[$i] === '{') {
                // Extract the token including nested braces
                $start = $i;
                $depth = 1;
                $i++;
                while ($i < $length && $depth > 0) {
                    if ($path[$i] === '{') {
                        $depth++;
                    } elseif ($path[$i] === '}') {
                        $depth--;
                    }
                    $i++;
                }

                if ($depth !== 0) {
                    throw new InvalidArgumentException("Unbalanced braces in route pattern.");
                }

                $tokenStr = substr($path, $start, $i - $start);
                $token = $this->extractTokens($tokenStr);
                $tokens[] = $token;
            } else {
                $i++;
            }
        }

        return $tokens;
    }


    /**
     * Parse a single token string like `{id}`, `{slug:[a-z]+}`, `{id:\d{1,3}}`.
     *
     * @param string $tokenStr
     * @return RouteToken
     */
    private function extractTokens(string $tokenStr): RouteToken
    {
        $content = substr($tokenStr, 1, -1); // remove braces
        $depth = 0;
        $name = '';
        $pattern = null;
        $buffer = '';
        $foundColon = false;

        $length = strlen($content);
        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

            if (!$foundColon) {
                if ($char === ':') {
                    $name = trim($buffer);
                    $buffer = '';
                    $foundColon = true;
                    continue;
                }
            }

            if ($foundColon) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    if ($depth > 0) {
                        $depth--;
                    }
                }
            }

            $buffer .= $char;
        }

        if (!$foundColon) {
            $name = trim($buffer);
        } else {
            $pattern = trim($buffer);
        }

        if (!preg_match('/^\w[\w-]*$/', $name)) {
            throw new InvalidArgumentException("Invalid parameter name: '$name'");
        }

        return new RouteToken($tokenStr, $name, $pattern);
    }

    /**
     * Generate the regex for all routes needed by the path.
     *
     * @param RouteToken[] $routeTokens
     * @return string
     */
    protected function parseVariableParts(array $routeTokens): string
    {
        $vars = [];
        $regex = $this->path;

        foreach ($routeTokens as $routeToken) {
            $name = $routeToken->name;
            $token = $routeToken->pattern ?? null;

            if (isset($vars[$name])) {
                throw new DuplicateAttributeException(
                    sprintf(
                        'Cannot use the same attribute twice [%s]',
                        $name
                    )
                );
            }

            $subpattern = $this->getSubpattern($name, $token);
            $regex = str_replace($routeToken->token, $subpattern, $regex);
            $vars[$name] = $name;
        }
        return $regex;
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
        if ($token) {
            return '(?P<' . $name . '>' . trim($token) . ')';
        }

        // use a default subpattern
        return '(?P<' . $name . '>[^/]+)';
    }
}
