<?php

declare(strict_types=1);

namespace Benchmarks;

use Pg\Router\Matcher\MarkDataMatcher;
use Pg\Router\Matcher\MatcherInterface;
use Pg\Router\Matcher\NamedMatcher;
use Pg\Router\RegexCollector\MarkRegexCollector;
use Pg\Router\RegexCollector\NamedRegexCollector;
use Pg\Router\RegexCollector\RegexCollectorInterface;
use Pg\Router\Route;
use RuntimeException;

class MatcherForBenchmark
{
    public static function realLifeExample(string $matcherClass): MatcherInterface
    {
        $collector = MatcherForBenchmark::setupRouter($matcherClass);


        $collector->addRoute(new Route('/', 'callback', 'home', ['GET']));
        $collector->addRoute(new Route('/page/{page_slug:[a-zA-Z0-9\-]+}', 'callback', 'page.show', ['GET']));
        $collector->addRoute(new Route('/about-us', 'callback', 'about-us', ['GET']));
        $collector->addRoute(new Route('/contact-us', 'callback', 'contact-us', ['GET']));
        $collector->addRoute(new Route('/contact-us', 'callback', 'contact-us.submit', ['POST']));
        $collector->addRoute(new Route('/blog', 'callback', 'blog.index', ['GET']));
        $collector->addRoute(new Route('/blog/recent', 'callback', 'blog.recent', ['GET']));
        $collector->addRoute(new Route('/blog/post/{post_slug:[a-zA-Z0-9\-]+}', 'callback', 'blog.post.show', ['GET']));
        $collector->addRoute(new Route(
            '/blog/post/{post_slug:[a-zA-Z0-9\-]+}/comment',
            'callback',
            'blog.post.comment',
            ['POST']
        ));
        $collector->addRoute(new Route('/shop', 'callback', 'shop.index', ['GET']));
        $collector->addRoute(new Route('/shop/category', 'callback', 'shop.category.index', ['GET']));
        $collector->addRoute(new Route(
            '/shop/category/search/{filter_by:[a-zA-Z]+}:{filter_value}',
            'callback',
            'shop.category.search',
            ['GET']
        ));
        $collector->addRoute(new Route('/shop/category/{category_id:\d+}', 'callback', 'shop.category.show', ['GET']));
        $collector->addRoute(new Route(
            '/shop/category/{category_id:\d+}/product',
            'callback',
            'shop.category.product.index',
            ['GET']
        ));
        $collector->addRoute(new Route(
            '/shop/category/{category_id:\d+}/product/search/{filter_by:[a-zA-Z]+}:{filter_value}',
            'callback',
            'shop.category.product.search',
            ['GET']
        ));
        $collector->addRoute(new Route('/shop/product', 'callback', 'shop.product.index', ['GET']));
        $collector->addRoute(new Route(
            '/shop/product/search/{filter_by:[a-zA-Z]+}:{filter_value}',
            'callback',
            'shop.product.search',
            ['GET']
        ));
        $collector->addRoute(new Route('/shop/product/{product_id:\d+}', 'callback', 'shop.product.show', ['GET']));
        $collector->addRoute(new Route('/shop/cart', 'callback', 'shop.cart.show', ['GET']));
        $collector->addRoute(new Route('/shop/cart', 'callback', 'shop.cart.add', ['PUT']));
        $collector->addRoute(new Route('/shop/cart', 'callback', 'shop.cart.empty', ['DELETE']));
        $collector->addRoute(new Route('/shop/cart/checkout', 'callback', 'shop.cart.checkout.show', ['GET']));
        $collector->addRoute(new Route('/shop/cart/checkout', 'callback', 'shop.cart.checkout.process', ['POST']));
        $collector->addRoute(new Route('/admin/login', 'callback', 'admin.login', ['GET']));
        $collector->addRoute(new Route('/admin/login', 'callback', 'admin.login.submit', ['POST']));
        $collector->addRoute(new Route('/admin/logout', 'callback', 'admin.logout', ['GET']));
        $collector->addRoute(new Route('/admin', 'callback', 'admin.index', ['GET']));
        $collector->addRoute(new Route('/admin/product', 'callback', 'admin.product.index', ['GET']));
        $collector->addRoute(new Route('/admin/product/create', 'callback', 'admin.product.create', ['GET']));
        $collector->addRoute(new Route('/admin/product', 'callback', 'admin.product.store', ['POST']));
        $collector->addRoute(new Route('/admin/product/{product_id:\d+}', 'callback', 'admin.product.show', ['GET']));
        $collector->addRoute(new Route(
            '/admin/product/{product_id:\d+}/edit',
            'callback',
            'admin.product.edit',
            ['GET']
        ));
        $collector->addRoute(new Route(
            '/admin/product/{product_id:\d+}',
            'callback',
            'admin.product.update',
            ['PUT', 'PATCH']
        ));
        $collector->addRoute(new Route(
            '/admin/product/{product_id:\d+}',
            'callback',
            'admin.product.destroy',
            ['DELETE']
        ));
        $collector->addRoute(new Route('/admin/category', 'callback', 'admin.category.index', ['GET']));
        $collector->addRoute(new Route('/admin/category/create', 'callback', 'admin.category.create', ['GET']));
        $collector->addRoute(new Route('/admin/category', 'callback', 'admin.category.store', ['POST']));
        $collector->addRoute(new Route(
            '/admin/category/{category_id:\d+}',
            'callback',
            'admin.category.show',
            ['GET']
        ));
        $collector->addRoute(new Route(
            '/admin/category/{category_id:\d+}/edit',
            'callback',
            'admin.category.edit',
            ['GET']
        ));
        $collector->addRoute(new Route(
            '/admin/category/{category_id:\d+}',
            'callback',
            'admin.category.update',
            ['PUT', 'PATCH']
        ));
        $collector->addRoute(new Route(
            '/admin/category/{category_id:\d+}',
            'callback',
            'admin.category.destroy',
            ['DELETE']
        ));

        return new $matcherClass($collector->getData());
    }
    public static function manyRoutes(string $matcherClass, int $routeCount = 400): MatcherInterface
    {
        $collector = MatcherForBenchmark::setupRouter($matcherClass);

        for ($i = 0; $i < $routeCount; ++$i) {
            $collector->addRoute(new Route('/abc' . $i, 'callback', 'static-' . $i, ['GET']));
            $collector->addRoute(new Route('/abc{foo}/' . $i, 'callback', 'not-static-' . $i, ['GET']));
        }

        return new $matcherClass($collector->getData());
    }
    /**
     */
    private static function setupRouter(string $matcherClass): RegexCollectorInterface
    {
        $config = null;
        return match ($matcherClass) {
            MarkDataMatcher::class =>  new MarkRegexCollector(),
            NamedMatcher::class => new NamedRegexCollector(),
            default => throw new RuntimeException("No matcher with this type $matcherClass")
        };
    }
}
