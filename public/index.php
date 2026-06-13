<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Start Session for Flash Messages & Auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Load the pre-built PHP-DI Container directly from the new config architecture
$container = require __DIR__ . '/../config/services.php';
AppFactory::setContainer($container);

$app = AppFactory::create();

// 2. Add Slim's HTTP routing & error handling middlewares
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// 3. Load cleanly decoupled decoupled web routing matrices
$routes = require __DIR__ . '/../routes/web.php';
$routes($app);

$app->run();