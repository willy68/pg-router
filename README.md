# pg-router

A fast, flexible, and PSR-7 compatible router for PHP.

## Features

- PSR-7 request/response support
- Route grouping and middleware stacking
- Named routes and URL generation
- CRUD route helpers
- Route caching for production
- Extensible matchers and collectors

## Installation

```bash
composer require pg/router
```

## Basic Usage

```php
use Pg\Router\Router;

$router = new Router();

$router->route('/hello/{name: \w+}', function ($request) {
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

## Route Groups and Middleware

```php
$router->group('/admin', function ($group) {
    $group->route('/dashboard', 'AdminController::dashboard', 'admin.dashboard', ['GET']);
})->middleware(AdminMiddleware::class);
```

## Optional Segments

You can define optional segments in your routes by enclosing them in square brackets `[]`. Optional segments should be separated by semicolon `;`.

```php
// Example route with optional segments
$router->route('/article[/{id};/{slug}]', function ($request) {
    $id = $request->getAttribute('id');
    $slug = $request->getAttribute('slug');
    // ...
}, 'article.show', ['GET']);
```

In this example, the following URLs will match:
- `/article`
- `/article/123`
- `/article/123/my-article`

Parameters will be available in the request based on the provided segments.

## CRUD Helper

```php
$router->crud('/posts', 'PostController', 'posts');
```

## URL Generation

```php
$url = $router->generateUri('hello', ['name' => 'Alice']);
```

## Testing

This project uses [PHPUnit](https://phpunit.de/) for testing.

```bash
./vendor/bin/phpunit
```

## License

MIT License