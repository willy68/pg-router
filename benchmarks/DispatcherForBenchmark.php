<?php

declare(strict_types=1);

namespace Benchmarks;

use Exception;
use FilesystemIterator;
use Pg\Router\Matcher\MatcherInterface;
use Pg\Router\Matcher\NamedMatcher;
use Pg\Router\RegexCollector\NamedRegexCollector;
use Pg\Router\Router;
use Psr\Cache\CacheException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

final class DispatcherForBenchmark
{
    public const CACHE_DIR = 'tmp/cache/router_bench';

    /**
     * @param string $router
     * @param bool $cache
     * @return Router
     * @throws CacheException
     */
    public static function realLifeExample(string $router, bool $cache = false): Router
    {
        $routes = DispatcherForBenchmark::setupRouter($router, $cache);


        $routes->route('/', 'callback', 'home', ['GET']);
        $routes->route('/page/{page_slug:[a-zA-Z0-9\-]+}', 'callback', 'page.show', ['GET']);
        $routes->route('/about-us', 'callback', 'about-us', ['GET']);
        $routes->route('/contact-us', 'callback', 'contact-us', ['GET']);
        $routes->route('/contact-us', 'callback', 'contact-us.submit', ['POST']);
        $routes->route('/blog', 'callback', 'blog.index', ['GET']);
        $routes->route('/blog/recent', 'callback', 'blog.recent', ['GET']);
        $routes->route('/blog/post/{post_slug:[a-zA-Z0-9\-]+}', 'callback', 'blog.post.show', ['GET']);
        $routes->route('/blog/post/{post_slug:[a-zA-Z0-9\-]+}/comment', 'callback', 'blog.post.comment', ['POST']);
        $routes->route('/shop', 'callback', 'shop.index', ['GET']);
        $routes->route('/shop/category', 'callback', 'shop.category.index', ['GET']);
        $routes->route(
            '/shop/category/search/{filter_by:[a-zA-Z]+}:{filter_value}',
            'callback',
            'shop.category.search',
            ['GET']
        );
        $routes->route('/shop/category/{category_id:\d+}', 'callback', 'shop.category.show', ['GET']);
        $routes->route('/shop/category/{category_id:\d+}/product', 'callback', 'shop.category.product.index', ['GET']);
        $routes->route(
            '/shop/category/{category_id:\d+}/product/search/{filter_by:[a-zA-Z]+}:{filter_value}',
            'callback',
            'shop.category.product.search',
            ['GET']
        );
        $routes->route('/shop/product', 'callback', 'shop.product.index', ['GET']);
        $routes->route(
            '/shop/product/search/{filter_by:[a-zA-Z]+}:{filter_value}',
            'callback',
            'shop.product.search',
            ['GET']
        );
        $routes->route('/shop/product/{product_id:\d+}', 'callback', 'shop.product.show', ['GET']);
        $routes->route('/shop/cart', 'callback', 'shop.cart.show', ['GET']);
        $routes->route('/shop/cart', 'callback', 'shop.cart.add', ['PUT']);
        $routes->route('/shop/cart', 'callback', 'shop.cart.empty', ['DELETE']);
        $routes->route('/shop/cart/checkout', 'callback', 'shop.cart.checkout.show', ['GET']);
        $routes->route('/shop/cart/checkout', 'callback', 'shop.cart.checkout.process', ['POST']);
        $routes->route('/admin/login', 'callback', 'admin.login', ['GET']);
        $routes->route('/admin/login', 'callback', 'admin.login.submit', ['POST']);
        $routes->route('/admin/logout', 'callback', 'admin.logout', ['GET']);
        $routes->route('/admin', 'callback', 'admin.index', ['GET']);
        $routes->route('/admin/product', 'callback', 'admin.product.index', ['GET']);
        $routes->route('/admin/product/create', 'callback', 'admin.product.create', ['GET']);
        $routes->route('/admin/product', 'callback', 'admin.product.store', ['POST']);
        $routes->route('/admin/product/{product_id:\d+}', 'callback', 'admin.product.show', ['GET']);
        $routes->route('/admin/product/{product_id:\d+}/edit', 'callback', 'admin.product.edit', ['GET']);
        $routes->route('/admin/product/{product_id:\d+}', 'callback', 'admin.product.update', ['PUT', 'PATCH']);
        $routes->route('/admin/product/{product_id:\d+}', 'callback', 'admin.product.destroy', ['DELETE']);
        $routes->route('/admin/category', 'callback', 'admin.category.index', ['GET']);
        $routes->route('/admin/category/create', 'callback', 'admin.category.create', ['GET']);
        $routes->route('/admin/category', 'callback', 'admin.category.store', ['POST']);
        $routes->route('/admin/category/{category_id:\d+}', 'callback', 'admin.category.show', ['GET']);
        $routes->route('/admin/category/{category_id:\d+}/edit', 'callback', 'admin.category.edit', ['GET']);
        $routes->route('/admin/category/{category_id:\d+}', 'callback', 'admin.category.update', ['PUT', 'PATCH']);
        $routes->route('/admin/category/{category_id:\d+}', 'callback', 'admin.category.destroy', ['DELETE']);

        return $routes;
    }

    /**
     * @throws CacheException
     */
    public static function manyRoutes(string $router, bool $cache = false, int $routeCount = 400): Router
    {
        $routes = DispatcherForBenchmark::setupRouter($router, $cache);

        for ($i = 0; $i < $routeCount; ++$i) {
            $routes->route('/abc' . $i, 'callback', 'static-' . $i, ['GET']);
            $routes->route('/abc{foo}/' . $i, 'callback', 'not-static-' . $i, ['GET']);
        }

        return $routes;
    }

    public static function delTree(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileInfo->getRealPath());
        }

        rmdir($dir);
        return true;
    }

    /**
     * Prepares router with cache enabled and 50 routes.
     * @throws CacheException
     * @throws Exception
     */
    private static function setupRouter(string $router, bool $cache): Router
    {
        $config = null;
        if ($cache) {
            $config =
                [
                    Router::CONFIG_CACHE_ENABLED => true,
                    Router::CONFIG_CACHE_DIR => DispatcherForBenchmark::CACHE_DIR,
                    Router::CONFIG_CACHE_POOL_FACTORY =>
                        fn() => new PhpFilesAdapter(
                            'RouterBench',
                            0,
                            DispatcherForBenchmark::CACHE_DIR
                        )
                ];
        }

        if ($router === 'mark') {
            return new Router(
                null,
                null,
                $config
            );
        } elseif ($router === 'named') {
            return new Router(
                new NamedRegexCollector(),
                fn($routes): MatcherInterface => new NamedMatcher($routes),
                $config
            );
        }
        return new Router();
    }
}
