<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

// 1. Ingest local environment attributes right away
require __DIR__ . '/../config/bootstrap.php';

// Start Session for Flash Messages & Auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Properly compile the PHP-DI Container using the raw definitions array
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/services.php');
$container = $containerBuilder->build();

// Assign the built container instance directly to Slim
AppFactory::setContainer($container);

$app = AppFactory::create();

// 3. Safely extract Twig from the compiled container to activate the view layer middleware
$twig = $container->get(Twig::class);
$app->add(TwigMiddleware::create($app, $twig));

// 4. Add Slim's HTTP routing & error handling middlewares
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    (getenv('APP_DEBUG') === 'true'), // Dynamic debug error toggle
    true, 
    true
);

// 5. Load cleanly decoupled web routing matrices
$routes = require __DIR__ . '/../routes/web.php';
$routes($app);

$app->run();