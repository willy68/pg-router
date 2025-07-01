<?php

declare(strict_types=1);

namespace Pg\Router\Generator;

use Pg\Router\Exception\MissingAttributeException;
use Pg\Router\Exception\RouteNotFoundException;
use Pg\Router\Exception\RuntimeException;
use Pg\Router\Regex\Regex;
use Pg\Router\Route;
use Pg\Router\RouteCollectionInterface;
use Pg\Router\RouterInterface;

/**
 * Générateur d'URL optimisé suivant les concepts du projet
 * Supporte les segments optionnels, variables et validation des tokens
 */
class FastUrlGenerator implements GeneratorInterface
{
    protected RouteCollectionInterface|RouterInterface $router;
    protected array $routeCache = [];

    public function __construct(RouteCollectionInterface|RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Génère une URL à partir du nom de route et des attributs
     *
     * @param string $name Nom de la route
     * @param array $attributes Attributs pour remplacer les variables
     * @return string URL générée
     * @throws RouteNotFoundException
     * @throws MissingAttributeException
     * @throws RuntimeException
     */
    public function generate(string $name, array $attributes = []): string
    {
        $route = $this->getRoute($name);
        $path = $route->getPath();

        // Cache des routes déjà analysées pour optimiser les performances
        if (!isset($this->routeCache[$name])) {
            $this->routeCache[$name] = $this->parseRoutePath($path);
        }

        $routeData = $this->routeCache[$name];

        return $this->buildUrl($routeData, $attributes, $name);
    }

    /**
     * Récupère la route par son nom
     *
     * @param string $name
     * @return Route
     * @throws RouteNotFoundException
     */
    protected function getRoute(string $name): Route
    {
        $route = $this->router->getRouteName($name);

        if (null === $route) {
            throw new RouteNotFoundException(
                sprintf('Route with name [%s] not found', $name)
            );
        }

        return $route;
    }

    /**
     * Analyse le chemin de la route et extrait la structure
     *
     * @param string $path
     * @return array
     */
    protected function parseRoutePath(string $path): array
    {
        $isOptionalStart = str_starts_with($path, '[');
        $pathWithoutClosing = rtrim($path, ']');

        // Séparer la partie statique des segments optionnels
        $parts = preg_split('~' . Regex::REGEX . '(*SKIP)(*F)|\[~x', $pathWithoutClosing);
        $basePath = (trim($parts[0]) ?? '') ?: '/';
        $optionalSegments = $parts[1] ?? '';

        return [
            'basePath' => $basePath,
            'optionalSegments' => $optionalSegments,
            'isOptionalStart' => $isOptionalStart,
            'baseVariables' => $this->extractVariables($basePath),
            'optionalVariables' => $optionalSegments ? $this->parseOptionalSegments($optionalSegments) : []
        ];
    }

    /**
     * Extrait les variables d'un segment de chemin
     *
     * @param string $segment
     * @return array
     */
    protected function extractVariables(string $segment): array
    {
        $variables = [];

        if (preg_match_all('~' . Regex::REGEX . '~x', $segment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $variables[] = [
                    'full' => $match[0],
                    'name' => $match[1],
                    'token' => $match[2] ?? '([^/]+)',
                ];
            }
        }

        return $variables;
    }

    /**
     * Parse les segments optionnels
     *
     * @param string $segments
     * @return array
     */
    protected function parseOptionalSegments(string $segments): array
    {
        $optionalParts = explode(';', $segments);
        $parsedSegments = [];

        foreach ($optionalParts as $index => $part) {
            $trimmedPart = trim($part);
            $parsedSegments[$index] = [
                'segment' => $trimmedPart,
                'variables' => $this->extractVariables($trimmedPart)
            ];
        }

        return $parsedSegments;
    }

    /**
     * Construit l'URL finale
     *
     * @param array $routeData
     * @param array $attributes
     * @param string $routeName
     * @return string
     */
    protected function buildUrl(array $routeData, array $attributes, string $routeName): string
    {
        // Cas spécial : route optionnelle au début sans attributs
        if ($routeData['isOptionalStart'] && empty($attributes)) {
            return '/';
        }

        $url = $routeData['basePath'];

        // Remplacer les variables de la partie statique
        if ($routeData['basePath'] !== '/') {
            $url = $this->replaceVariables($url, $routeData['baseVariables'], $attributes, $routeName);
        }

        // Traiter les segments optionnels
        if (!empty($routeData['optionalVariables'])) {
            $optionalUrl = $this->buildOptionalSegments($routeData, $attributes, $routeName);
            $url .= $optionalUrl;
        }

        return $url;
    }

    /**
     * Remplace les variables dans un segment
     *
     * @param string $segment
     * @param array $variables
     * @param array $attributes
     * @param string $routeName
     * @return string
     */
    protected function replaceVariables(string $segment, array $variables, array $attributes, string $routeName): string
    {
        $replacements = [];

        foreach ($variables as $variable) {
            $name = $variable['name'];
            $token = $variable['token'];

            if (!isset($attributes[$name])) {
                throw new MissingAttributeException(sprintf(
                    'Parameter value for [%s] is missing for route [%s]',
                    $name,
                    $routeName
                ));
            }

            $value = (string) $attributes[$name];

            // Validation du token
            if (!preg_match('~^' . $token . '$~x', $value)) {
                throw new RuntimeException(sprintf(
                    'Parameter value for [%s] did not match the regex `%s` in route [%s]',
                    $name,
                    $token,
                    $routeName
                ));
            }

            $replacements[$variable['full']] = rawurlencode($value);
        }

        return strtr($segment, $replacements);
    }

    /**
     * Construit les segments optionnels
     *
     * @param array $routeData
     * @param array $attributes
     * @param string $routeName
     * @return string
     */
    protected function buildOptionalSegments(array $routeData, array $attributes, string $routeName): string
    {
        $optionalUrl = '';
        $segments = $routeData['optionalVariables'];

        foreach ($segments as $index => $segmentData) {
            $segment = $segmentData['segment'];
            $variables = $segmentData['variables'];

            // Vérifier si tous les paramètres requis sont présents
            $canBuild = true;
            foreach ($variables as $variable) {
                if (!isset($attributes[$variable['name']])) {
                    $canBuild = false;
                    break; // Les segments optionnels sont séquentiels.
                }
            }

            if (!$canBuild) {
                break;
            }

            // Ajuster le préfixe pour le premier segment optionnel si nécessaire
            if ($index === 0 && $routeData['isOptionalStart'] && str_starts_with(ltrim($segment), '/')) {
                $segment = ltrim($segment, '/');
            }

            $builtSegment = $this->replaceVariables($segment, $variables, $attributes, $routeName);
            $optionalUrl .= $builtSegment;
        }

        return $optionalUrl;
    }

    /**
     * Vide le cache des routes (utile pour les tests ou le développement)
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->routeCache = [];
    }

    /**
     * Retourne les informations de cache pour une route (utile pour le debug)
     *
     * @param string $name
     * @return array|null
     */
    public function getRouteCache(string $name): ?array
    {
        return $this->routeCache[$name] ?? null;
    }
}
