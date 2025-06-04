<?php

namespace Pg\Router\Matcher;

use Pg\Router\Regex\Regex;
use Psr\Http\Message\ServerRequestInterface;

class RequestMatcher implements RequestMatcherInterface
{
    private ?string $path;
    private array $methods = [];
    private ?string $host;
    private array $schemes = [];
    private ?string $port;
    private ?array $matchedParams = null;

    public function __construct(
        string $path = null,
        array $methods = null,
        string $host = null,
        array $schemes = null,
        string $port = null
    ) {
        $this->setPath($path);
        $this->setMethod($methods);
        $this->setHost($host);
        $this->setSchemes($schemes);
        $this->setPort($port);
    }

    public function match(ServerRequestInterface $request): bool
    {
        if (!empty($this->schemes) && !in_array($request->getUri()->getScheme(), $this->schemes, true)) {
            return false;
        }

        if (!empty($this->methods) && !in_array($request->getMethod(), $this->methods, true)) {
            return false;
        }
        // Chemin
        if ($this->path !== null) {
            $path = $request->getUri()->getPath();
            $regex = $this->convertPathToRegex($this->path);

            if (!preg_match($regex, $path, $matches)) {
                return false;
            }

            // Extraire les groupes nommés uniquement
            $this->matchedParams = array_filter(
                $matches,
                fn($key) => !is_int($key),
                ARRAY_FILTER_USE_KEY
            );
        }

        if (null !== $this->host && !preg_match('{' . $this->host . '}i', $request->getUri()->getHost())) {
            return false;
        }

        if (null !== $this->port && 0 < $this->port && $request->getUri()->getPort() !== $this->port) {
            return false;
        }

        return true;
    }

    /**
     * Retourne les paramètres capturés depuis le chemin.
     * Exemple : /users/42 return ['id' => '42']
     */
    public function getPathParams(): array
    {
        return $this->matchedParams ?? [];
    }

    /**
     * Convertit un chemin type /users/{id} en regex : #^/users/(?P<id>[^/]+)#
     * Convertit un chemin type /users/{id:\d+} en regex : #^/users/(?P<id>\d+)#
     */
    private function convertPathToRegex(string $pattern): string
    {
        $regex = preg_replace_callback(
            Regex::REGEX,
            fn($matches) => '(?P<' . $matches[1] . '>' . (isset($matches[2]) ? $matches[2] . ')' : '[^/]+)'),
            $pattern
        );
        return '#^' . $regex . '#';
    }

    /**
     * Set the value of path
     *
     * @param string|null $path
     * @return  self
     */
    public function setPath(?string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Set the value of method
     *
     * @param $method
     * @return  self
     */
    public function setMethod($method): static
    {
        $this->methods = null !== $method ? array_map('strtoupper', (array) $method) : [];

        return $this;
    }

    /**
     * Set the value of host
     *
     * @param string|null $host
     * @return  self
     */
    public function setHost(?string $host): static
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Set the value of scheme
     *
     * @param $scheme
     * @return  self
     */
    public function setSchemes($scheme): static
    {
        $this->schemes = null !== $scheme ? array_map('strtolower', (array) $scheme) : [];

        return $this;
    }

    /**
     * Set the value of port
     *
     * @param string|null $port
     * @return  self
     */
    public function setPort(?string $port): static
    {
        $this->port = $port;

        return $this;
    }
}
