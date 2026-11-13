<?php

declare(strict_types=1);

/*
 * Test only, never deployed: the router for PHP's built-in server in the browser tests
 * (started by browserPanel() in tests/Browser/panel.php). Files under public/ are served
 * as they are; everything else goes to the real control panel. It adds one route of its
 * own, /cp/__test/sign-in, so the tests need not go through two-factor every time.
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Http\ControlPanel\ControlPanel;
use SlimKiller\Http\Session\Session;

$root = dirname(__DIR__, 2);
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($path !== '/' && is_file($root . '/public' . $path)) {
    return false;
}

require $root . '/vendor/autoload.php';
require __DIR__ . '/panel.php';

// The publishing report a test set (publishingReport()), instead of asking GitHub.
$report = getenv('SK_BROWSER_STATE') . '/publishing-report.json';
if ($path === '/cp/publishing') {
    header('Content-Type: application/json');
    echo is_file($report) ? file_get_contents($report) : '{"stage":"none","message":"","done":true}';

    return true;
}

$container = browserContainer((string) getenv('SK_BROWSER_STATE'));
$app = ControlPanel::create($container, showErrorDetails: true, logErrors: false);

// Not a static function: Slim binds a route's function to the container.
$app->get('/__test/sign-in', function (ServerRequestInterface $request, ResponseInterface $response) use ($container): ResponseInterface {
    $user = $container->get(UserRepository::class)->byEmail(BROWSER_USER);
    if ($user === null) {
        return $response->withStatus(500);
    }
    $session = Session::of($request);
    $session->regenerate();
    $session->set(Session::USER, $user->id);
    $next = (string) ($request->getQueryParams()['next'] ?? '/cp/');

    return $response->withStatus(303)->withHeader('Location', str_starts_with($next, '/cp/') ? $next : '/cp/');
});

$app->run();
