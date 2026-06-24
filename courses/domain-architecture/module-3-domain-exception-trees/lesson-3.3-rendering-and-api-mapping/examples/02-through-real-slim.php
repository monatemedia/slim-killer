<?php

declare(strict_types=1);

/**
 * Example 02 — End to end through REAL Slim (the capstone)
 * -------------------------------------------------------------------------
 * A genuine Slim 4 pipeline: addErrorMiddleware + our DomainErrorHandler, driven by a
 * synthetic request (no socket needed). This is exactly the wiring you would add to
 * Slim Killer's public/index.php — proven to produce a clean 422 Problem Details body.
 *
 *   php examples/02-through-real-slim.php
 *
 * Requires the project's Composer dependencies (Slim). If vendor/ is missing, run
 * `composer install` at the project root first.
 */

$projectRoot = dirname(__DIR__, 5);
$composer = $projectRoot . '/vendor/autoload.php';

if (!is_file($composer)) {
    fwrite(STDERR, "Slim not installed — run `composer install` at the project root, then retry.\n");
    exit(0);
}

require $composer;            // Slim + PSR (App\ -> src/)
require __DIR__ . '/../autoload.php';   // Bond\ -> this lesson's src/

use Bond\Domain\Exception\ApplicantHasInsufficientIncomeException;
use Bond\Http\Slim\DomainErrorHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

echo "=== Example 02 — Through Real Slim ===\n\n";

$app = AppFactory::create();
$app->addRoutingMiddleware();

// PRODUCTION posture: displayErrorDetails = false (no stack traces to the client).
$errorMiddleware = $app->addErrorMiddleware(false, true, true);

// One handler for everything; the mapper decides domain (422/409/404) vs server (500).
$handler = new DomainErrorHandler($app->getResponseFactory(), debug: false);
$errorMiddleware->setDefaultErrorHandler($handler);

// Two routes that fail in the two categories.
$app->post('/apply', function (Request $request, Response $response) {
    throw new ApplicantHasInsufficientIncomeException('11111111-1111-4111-8111-111111111111', 3_000_000, 1_333_038);
});
$app->get('/boom', function (Request $request, Response $response) {
    throw new RuntimeException('SQLSTATE[HY000] connect failed: host=10.0.0.5 password=hunter2');
});

$send = function (string $method, string $path) use ($app): void {
    $request = (new ServerRequestFactory())->createServerRequest($method, $path);
    $response = $app->handle($request);
    echo "{$method} {$path}\n";
    echo "  -> {$response->getStatusCode()} ; Content-Type: {$response->getHeaderLine('Content-Type')}\n";
    echo "  body: " . trim(preg_replace('/\s+/', ' ', (string) $response->getBody())) . "\n\n";
};

// A business failure -> a clean, specific 422.
$send('POST', '/apply');

// A technical failure -> a generic 500, with the secret kept OUT of the body.
$send('GET', '/boom');

echo "Server-side log (full detail, never sent to the client):\n";
foreach ($handler->log as $line) {
    echo "  • {$line}\n";
}
echo "\nThe /boom response carried only a reference; its DSN + password live in the log above.\n";
