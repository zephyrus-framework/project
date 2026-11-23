<?php

declare(strict_types=1);

namespace App\Models\Core;

use Dotenv\Dotenv;
use Zephyrus\Core\ApplicationBuilder;
use Zephyrus\Core\Config\Configuration;
use Zephyrus\Http\Request;
use Zephyrus\Mailer\MailerConfig;
use Zephyrus\Rendering\LatteEngine;
use Zephyrus\Rendering\RenderConfig;
use Zephyrus\Routing\Router;
use Zephyrus\Security\CsrfConfig;
use Zephyrus\Security\CsrfMiddleware;
use Zephyrus\Security\MaxBodySizeMiddleware;
use Zephyrus\Security\SecureHeadersMiddleware;
use Zephyrus\Session\SessionCsrfTokenManager;
use Zephyrus\Session\SessionManager;
use Zephyrus\Session\SessionMiddleware;

/**
 * Abstract application kernel that encapsulates the full bootstrap lifecycle.
 *
 * Controllers are discovered automatically from app/Controllers/ by default.
 * Override registerControllers() only if you need custom registration logic.
 *
 * In index.php:
 *
 *   (new Application())->run();
 */
abstract class Kernel
{
    protected Configuration $config;
    protected LatteEngine $renderEngine;
    protected SessionManager $session;
    protected SessionCsrfTokenManager $csrfTokens;

    public function __construct()
    {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 3));
        }
        $this->boot();
    }

    /**
     * Handle the current HTTP request and send the response.
     */
    public function run(): void
    {
        $router = $this->registerControllers(new Router());

        $builder = ApplicationBuilder::create()
            ->withConfiguration($this->config, basePath: ROOT_DIR)
            ->withRouter($router);

        $builder = $this->configureErrorHandlers($builder);

        $renderEngine = $this->renderEngine;
        $builder = $builder->withControllerFactory(function (string $class) use ($renderEngine): object {
            $controller = new $class();
            if (method_exists($controller, 'setRenderEngine')) {
                $controller->setRenderEngine($renderEngine);
            }
            return $controller;
        });

        $builder = $this->registerMiddleware($builder);

        $app = $builder->build();

        $security = $this->config->security;
        $request = Request::fromGlobals(
            trustedProxies: $security->trustedProxies,
            trustedHeaders: $security->trustedHeaders,
            maxBodySize: $security->maxBodySize,
        );
        $response = $app->handle($request);
        $response->send();
    }

    /**
     * Discover and register controllers with the router.
     *
     * By default, all concrete classes under app/Controllers/ with route
     * attributes (#[Get], #[Post], etc.) are registered automatically.
     *
     * Override this method to add manual registrations, apply groups, or
     * restrict discovery:
     *
     *   protected function registerControllers(Router $router): Router
     *   {
     *       return parent::registerControllers($router)
     *           ->group('/api', fn (Router $r) => $r
     *               ->controller(ApiController::class));
     *   }
     */
    protected function registerControllers(Router $router): Router
    {
        return $router->discoverControllers(
            namespace: 'App\\Controllers',
            directory: ROOT_DIR . '/app/Controllers',
        );
    }

    /**
     * Register global and named middleware, the first added running outermost.
     *
     * The default mounts the secure headers, the body size limit, the session and
     * the CSRF check. An override calls parent::registerMiddleware() first: build()
     * refuses to boot when a security setting of config.yml has no middleware.
     */
    protected function registerMiddleware(ApplicationBuilder $builder): ApplicationBuilder
    {
        $security = $this->config->security;

        return $builder
            ->withMiddleware(new SecureHeadersMiddleware($security->headers))
            ->withMiddleware(new MaxBodySizeMiddleware($security->maxBodySize))
            ->withMiddleware(new SessionMiddleware($this->config->session, $this->session))
            ->withMiddleware(new CsrfMiddleware($this->csrfTokens, CsrfConfig::fromSecurityConfig($security)));
    }

    /**
     * Configure custom exception handlers.
     *
     * Override to register custom exception handlers via the builder's
     * kernel builder. Default is a no-op.
     */
    protected function configureErrorHandlers(ApplicationBuilder $builder): ApplicationBuilder
    {
        return $builder;
    }

    /**
     * Bootstrap environment, configuration, session and render engine.
     */
    private function boot(): void
    {
        Dotenv::createImmutable(ROOT_DIR)->safeLoad();

        $this->config = Configuration::fromYamlFile(ROOT_DIR . '/config.yml', [
            'render' => RenderConfig::class,
            'mailer' => MailerConfig::class,
            'project' => ProjectConfig::class,
        ]);

        $this->session = new SessionManager();
        $this->csrfTokens = new SessionCsrfTokenManager($this->session);

        /** @var RenderConfig $renderConfig */
        $renderConfig = $this->config->section('render') ?? RenderConfig::fromArray([]);
        $this->renderEngine = $renderConfig->createEngine(ROOT_DIR);
        $this->renderEngine->getLatteEngine()->addFunction('csrfToken', $this->csrfTokens->getToken(...));
    }
}
