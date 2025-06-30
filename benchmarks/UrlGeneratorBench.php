<?php

declare(strict_types=1);

namespace Benchmarks;

use Exception;
use PhpBench\Attributes as Bench;
use Pg\Router\Generator\FastUrlGenerator;
use Pg\Router\Generator\UrlGenerator;
use Pg\Router\RouteCollector;
use Pg\Router\Router;

/**
 * Benchmark comparatif des générateurs d'URL
 * Mesure les performances entre UrlGenerator et FastUrlGenerator
 */
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
#[Bench\Warmup(2)]
class UrlGeneratorBench
{
    private ?UrlGenerator $standardGenerator = null;
    private ?FastUrlGenerator $fastGenerator = null;

    /**
     * Setup initial avec routes diverses pour les benchmarks
     */
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function setupGenerators(): void
    {
        $router = new Router();
        $collector = null;
        $collector = new RouteCollector($router);

        // Routes de test variées
        $collector->route('/static/page', 'StaticController', 'static_page');
        $collector->route('/user/{id}', 'UserController', 'user_show');
        $collector->route('/blog/{slug:[a-z0-9-]+}', 'BlogController', 'blog_post');
        $collector->route('/api/v1/users/{id:\d+}/posts/{postId:\d+}', 'ApiController', 'api_user_post');
        $collector->route(
            '/archive/{year:\d{4}}[/{month:\d{2}};/{day:\d{2}}]',
            'ArchiveController',
            'archive_posts'
        );
        $collector->route('[/{category:[a-z]+};/page/{page:\d+}]', 'CatalogController', 'catalog_list');
        $collector->route('/admin/users/{id:\d+}/edit', 'AdminController', 'admin_user_edit');
        $collector->route('https://{subdomain}.example.com/api/{version}', 'ExternalController', 'external_api');

        // Créer les générateurs
        $this->standardGenerator = new UrlGenerator($collector);
        $this->fastGenerator = new FastUrlGenerator($collector);
    }

    /**
     * Benchmark génération URL statique - UrlGenerator standard
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorStatic(): void
    {
        $this->standardGenerator->generate('static_page');
    }

    /**
     * Benchmark génération URL statique - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorStatic(): void
    {
        $this->fastGenerator->generate('static_page');
    }

    /**
     * Benchmark génération URL simple avec variable - UrlGenerator
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorSimpleVariable(): void
    {
        $this->standardGenerator->generate('user_show', ['id' => 42]);
    }

    /**
     * Benchmark génération URL simple avec variable - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorSimpleVariable(): void
    {
        $this->fastGenerator->generate('user_show', ['id' => 42]);
    }

    /**
     * Benchmark génération URL avec token complexe - UrlGenerator
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorComplexToken(): void
    {
        $this->standardGenerator->generate('blog_post', ['slug' => 'mon-super-article-2024']);
    }

    /**
     * Benchmark génération URL avec token complexe - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorComplexToken(): void
    {
        $this->fastGenerator->generate('blog_post', ['slug' => 'mon-super-article-2024']);
    }

    /**
     * Benchmark génération URL API complexe - UrlGenerator
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorApiRoute(): void
    {
        $this->standardGenerator->generate('api_user_post', [
            'id' => 123,
            'postId' => 456
        ]);
    }

    /**
     * Benchmark génération URL API complexe - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorApiRoute(): void
    {
        $this->fastGenerator->generate('api_user_post', [
            'id' => 123,
            'postId' => 456
        ]);
    }

    /**
     * Benchmark génération URL avec segments optionnels - UrlGenerator
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorOptionalSegments(): void
    {
        $this->standardGenerator->generate('archive_posts', [
            'year' => '2024',
            'month' => '12',
            'day' => '25'
        ]);
    }

    /**
     * Benchmark génération URL avec segments optionnels - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorOptionalSegments(): void
    {
        $this->fastGenerator->generate('archive_posts', [
            'year' => '2024',
            'month' => '12',
            'day' => '25'
        ]);
    }

    /**
     * Benchmark génération URL optionnelle au début - UrlGenerator
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorOptionalStart(): void
    {
        $this->standardGenerator->generate('catalog_list', [
            'category' => 'electronics',
            'page' => 3
        ]);
    }

    /**
     * Benchmark génération URL optionnelle au début - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorOptionalStart(): void
    {
        $this->fastGenerator->generate('catalog_list', [
            'category' => 'electronics',
            'page' => 3
        ]);
    }

    /**
     * Benchmark génération URL avec host/domaine - UrlGenerator
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorWithHost(): void
    {
        $this->standardGenerator->generate('external_api', [
            'subdomain' => 'api',
            'version' => 'v2'
        ]);
    }

    /**
     * Benchmark génération URL avec host/domaine - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorWithHost(): void
    {
        $this->fastGenerator->generate('external_api', [
            'subdomain' => 'api',
            'version' => 'v2'
        ]);
    }

    /**
     * Test de performance sur génération répétée avec cache - FastUrlGenerator
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchFastGeneratorRepeatedCalls(): void
    {
        // Première génération - construit le cache
        $this->fastGenerator->generate('blog_post', ['slug' => 'article-1']);

        // Générations suivantes - utilise le cache
        for ($i = 2; $i <= 10; $i++) {
            $this->fastGenerator->generate('blog_post', ['slug' => "article-$i"]);
        }
    }

    /**
     * Test de performance sur génération répétée sans cache - UrlGenerator
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\BeforeMethods(['setupGenerators'])]
    public function benchStandardGeneratorRepeatedCalls(): void
    {
        // Générations multiples sans cache
        for ($i = 1; $i <= 10; $i++) {
            $this->standardGenerator->generate('blog_post', ['slug' => "article-$i"]);
        }
    }

    /**
     * Benchmark avec volume important de routes
     *
     * @param array{count: int, target: string} $params
     */
    #[Bench\Subject]
    #[Bench\ParamProviders(['provideRouteVolumes'])]
    public function benchFastGeneratorVolume(array $params): void
    {
        $router = new Router();
        $collector = new RouteCollector($router);

        // Créer beaucoup de routes
        for ($i = 1; $i <= $params['count']; $i++) {
            $collector->route("/route$i/{id}", "Controller$i", "route_$i");
        }

        $generator = new FastUrlGenerator($collector);
        $generator->generate($params['target'], ['id' => 123]);
    }

    /**
     * Benchmark avec volume important de routes - standard
     *
     * @param array{count: int, target: string} $params
     * @throws Exception
     */
    #[Bench\Subject]
    #[Bench\ParamProviders(['provideRouteVolumes'])]
    public function benchStandardGeneratorVolume(array $params): void
    {
        $router = new Router();
        $collector = new RouteCollector($router);

        // Créer beaucoup de routes
        for ($i = 1; $i <= $params['count']; $i++) {
            $collector->route("/route$i/{id}", "Controller$i", "route_$i");
        }

        $generator = new UrlGenerator($collector);
        $generator->generate($params['target'], ['id' => 123]);
    }

    /**
     * Fournit différents volumes de routes pour les tests
     */
    public function provideRouteVolumes(): array
    {
        return [
            'small_volume' => ['count' => 50, 'target' => 'route_25'],
            'medium_volume' => ['count' => 200, 'target' => 'route_100'],
            'large_volume' => ['count' => 500, 'target' => 'route_250'],
        ];
    }
}
