# pg-router

[![Latest Stable Version](http://poser.pugx.org/willy68/pg-router/v)](https://packagist.org/packages/willy68/pg-router)
[![Total Downloads](http://poser.pugx.org/willy68/pg-router/downloads)](https://packagist.org/packages/willy68/pg-router)
[![Latest Unstable Version](http://poser.pugx.org/willy68/pg-router/v/unstable)](https://packagist.org/packages/willy68/pg-router)
[![License](http://poser.pugx.org/willy68/pg-router/license)](https://packagist.org/packages/willy68/pg-router)
[![PHP Version Require](http://poser.pugx.org/willy68/pg-router/require/php)](https://packagist.org/packages/willy68/pg-router)
[![Coverage Status](https://coveralls.io/repos/github/willy68/pg-router/badge.svg?branch=main)](https://coveralls.io/github/willy68/pg-router?branch=main)

A fast, flexible, and PSR-7 compatible HTTP router for PHP applications. Built for performance with support for advanced routing patterns, middleware stacking, and route caching.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Advanced Usage](#advanced-usage)
    - [Route Parameters](#route-parameters)
    - [Optional Segments](#optional-segments)
    - [Route Groups](#route-groups)
    - [Middleware](#middleware)
    - [Named Routes](#named-routes)
    - [CRUD Helper](#crud-helper)
    - [URL Generation](#url-generation)
- [Route Caching](#route-caching)
- [Performance](#performance)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features

- ✅ **PSR-7 Compatible**: Full support for PSR-7 request interfaces
- 🚀 **High Performance**: Optimized route matching with caching support
- 🎯 **Flexible Routing**: Support for complex route patterns and constraints
- 🔧 **Middleware Support**: Route-level and group-level middleware stacking
- 📦 **Route Grouping**: Organize routes with shared prefixes and middlewares
- 🎨 **Named Routes**: Easy URL generation with named route support
- ⚡ **CRUD Helpers**: Quick REST resource route generation
- 🔀 **Optional Segments**: Advanced optional route segment support
- 🏗️ **Extensible**: Custom matchers and collectors for advanced use cases

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

Install via Composer:

```bash
composer require willy68/pg-router
```

## Quick Start


```php
use Pg\Router\Router;
use guzzlehttp\Psr7\Response;
use guzzlehttp\Psr7\ServerRequest;

$request = ServerRequest::fromGlobals(); // Create a PSR-7 request from global variables
$router = new Router();

$router->route('/hello/{name: \w+}', function ($request): ResponseInterface {
    $name = $request->getAttribute('name');
    (return new Response())->getBody()->write("Hello, $name!");
}, 'hello', ['GET']);

$res = $router->match($request);

if ($res->isSuccess()) {
// add route attributes to the request
    foreach ($res->getMatchedAttributes() as $key => $val) {
        $request = $request->withAttribute($key, $val);
    }

$callback = $res->getMatchedRoute()->getCallback();
$response = $callback($request);
```

## Advanced Usage


### Route Parameters

```php
// Simple parameter, id matches any alphanumeric string
$router->route('/user/{id}','handler', 'user.show', ['GET']);

//Define route parameters with custom patterns:
// Parameter with regex constraint 
$router->route('/user/{id:\d+}','handler', 'user.show', ['GET']);

// Multiple parameters 
$router->route('/blog/{year:\d{4}}/{month:\d{2}}/{slug}','handler', 'blog.post', ['GET']);
```
It is possible to define default tokens for parameters:
```php
// The id matches a numeric string for this route
$router->route('/user/{id}','handler', 'user.show', ['GET'])
    ->setTokens(['id' => '\d+']);

// Default token for all routes
$router->setTokens(['id' => '\d+']);

// Update and/or add tokens given by the Router
$route->updateTokens(['id' => '[0-9]', 'slug' => '[a-zA-Z0-9_-]+']);

```

### Optional Segments

**Breaking Change**: Optional segments now use a new syntax with `[!...;...]` pattern:

```php
// Example route with optional segments
$router->route('/article[!/{id: \d+};/{slug: [\w-]+}]', function ($request) {
    $id = $request->getAttribute('id', null);
    $slug = $request->getAttribute('slug', null);
    // ...
}, 'article.show', ['GET']);
```

This route matches:
- `/article` (no parameters)
- `/article/123` (id parameter only)
- `/article/123/my-article-title` (both id and slug parameters)

Parameters will be available in the request based on the provided segments.

### Route Groups

Organize related routes with shared prefixes and middlewares:

```php
$router->group('/api/v1', function ($group) { 
    $group->route('/users', 'UserController::index', 'api.users.index', ['GET']);
    $group->route('/users/{id:\d+}', 'UserController::show', 'api.users.show', ['GET']); 
    $group->route('/users', 'UserController::store', 'api.users.store', ['POST']); 
})->middlewares([AuthMiddleware::class, ApiMiddleware::class]);

```

### Middleware

Apply middleware to individual routes or groups:
```PHP
// Route-level middleware
$router->route('/admin/dashboard','handler', 'admin.dashboard', ['GET'])
    ->middlewares([AuthMiddleware::class, AdminMiddleware::class]);

// Group-level middleware
$router->group('/admin', function ($group) { 
    $group->route('/users', 'AdminController::users', 'admin.users', ['GET']);
    $group->route('/settings', 'AdminController::settings', 'admin.settings', ['GET']);
})->middlewares([AuthMiddleware::class, AdminMiddleware::class]);

```

### Named Routes

Generate URLs using named routes:
```php
// Define a named route
$router->route('/user/{id:\d+}','handler', 'user.profile', ['GET']);

// Generate URL
$url = $router->generateUri('user.profile', ['id' => 123]); 
// Result: /user/123

// Generate URL with query parameters
$url = $router->generateUri('user.profile', ['id' => 123], ['tab' => 'settings']); 
// Result: /user/123?tab=settings

```

### CRUD Helper

```php
$router->crud('/posts', PostController::class, 'posts');
```

This creates the following routes:
- `GET /posts` → `PostController::index` (posts.index)
- `GET /posts/new` → `PostController::create` (posts.create)
- `POST /posts/new` → `PostController::create` (posts.create.post)
- `GET /posts/{id:\d+}` → `PostController::edit` (posts.edit)
- `POST /posts/{id:\d+}` → `PostController::edit` (posts.edit.post)
- `DELETE /posts/{id:\d+}` → `PostController::delete` (posts.delete)

### URL Generation

```php
// Basic URL generation
$url = $router->generateUri('hello', ['name' => 'Alice']);
// With query parameters
$url = $router->generateUri('user.profile', ['id' => 123], ['tab' => 'settings']);

```

## Route Caching

Enable route caching for production environments:

```php
// Enable caching with a cache file
      $router = new Router (
           null,
           null,
           [
               Router::CONFIG_CACHE_ENABLED => ($env === 'prod'),
               Router::CONFIG_CACHE_DIR => '/tmp/cache',
               Router::CONFIG_CACHE_POOL_FACTORY => function (): CacheItemPoolInterface {...},
           ]
      )

// In production, routes are cached and loaded from the cache file
// In development, disable caching or clear cache when routes change
$router->clearCache();
```
`Router::CONFIG_CACHE_POOL_FACTORY` allows you to use a custom PSR-6 compatible cache pool implementation,  
but this parameter is optional.

## Performance
pg-router is designed for high performance:
- **Optimized Route Matching**: Uses efficient algorithms for route compilation and matching
- **Route Caching**: Cache compiled routes for production use
- **Minimal Memory Footprint**: Efficient memory usage for large route tables
- **Fast Parameter Extraction**: Optimized parameter extraction from matched routes

## Testing

This project uses [PHPUnit](https://phpunit.de/) for testing.
To run the tests, ensure you have PHPUnit installed via Composer:

```bash
# Run all tests
./vendor/bin/phpunit

# Run tests with coverage
php -dxdebug.mode=coverage ./vendor/bin/phpunit --coverage-text --coverage-html=build/coverage
# or
composer run coverage

# Run specific test file
./vendor/bin/phpunit tests/RouterTest.php
```
## Contributing

Contributions are welcome! Please follow these guidelines:
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure your code follows PSR-12 coding standards and includes appropriate tests.

## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

**Author**: William Lety
**Maintainer**: [willy68](https://github.com/willy68)
**Repository**: [pg-router](https://github.com/willy68/pg-router)
