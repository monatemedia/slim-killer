<?php

declare(strict_types=1);

namespace SlimKiller\Http\ControlPanel;

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use SlimKiller\Http\ControlPanel\Actions\HomeAction;
use SlimKiller\Http\ControlPanel\Actions\SetupAction;

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
        $app->get('/setup', SetupAction::class);

        $app->addRoutingMiddleware();
        $app->addErrorMiddleware($showErrorDetails, $logErrors, $logErrors);

        // Added last, so it runs first and wraps error pages too.
        $app->add(SecurityHeaders::class);

        return $app;
    }
}
