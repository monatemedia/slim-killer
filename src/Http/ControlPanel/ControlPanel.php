<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use SlimKiller\Http\ControlPanel\Actions\HomeAction;
use SlimKiller\Http\ControlPanel\Actions\SetupAction;
use SlimKiller\Http\ControlPanel\Actions\SignInAction;
use SlimKiller\Http\ControlPanel\Actions\SignOutAction;
use SlimKiller\Http\ControlPanel\Actions\TwoFactorAction;
use SlimKiller\Http\ControlPanel\Actions\TwoFactorSetupAction;
use SlimKiller\Http\ControlPanel\Actions\WelcomeAction;
use SlimKiller\Http\Session\CsrfMiddleware;
use SlimKiller\Http\Session\SessionMiddleware;

/**
 * Builds the control panel's Slim app from a ready container. The container itself
 * is built at the entry point, public/cp/index.php (BUILD_PHILOSOPHY.md, Rule 1).
 */
final class ControlPanel
{
    /** Where the control panel lives on every client's domain. */
    public const string BASE_PATH = '/cp';

    /**
     * @param bool $showErrorDetails Stack traces in error pages: tests and local work only.
     * @param bool $logErrors Errors to PHP's error log: on in real use, off in tests.
     * @return App<ContainerInterface>
     */
    public static function create(ContainerInterface $container, bool $showErrorDetails = false, bool $logErrors = true): App
    {
        $app = AppFactory::createFromContainer($container);
        $app->setBasePath(self::BASE_PATH);

        $app->redirect('', self::BASE_PATH . '/', 301);
        $app->get('/', HomeAction::class);
        $app->map(['GET', 'POST'], '/sign-in', SignInAction::class);
        $app->map(['GET', 'POST'], '/two-factor', TwoFactorAction::class);
        $app->map(['GET', 'POST'], '/two-factor/setup', TwoFactorSetupAction::class);
        $app->map(['GET', 'POST'], '/welcome', WelcomeAction::class);
        $app->post('/sign-out', SignOutAction::class);
        $app->get('/setup', SetupAction::class);

        // Middleware runs in the reverse of the order it is added. On the way in:
        // security headers, errors, routing, session, form parsing, the CSRF check.
        $app->add(CsrfMiddleware::class);
        $app->addBodyParsingMiddleware();
        $app->add(SessionMiddleware::class);
        $app->addRoutingMiddleware();
        $app->addErrorMiddleware($showErrorDetails, $logErrors, $logErrors);
        $app->add(SecurityHeaders::class);

        return $app;
    }
}
