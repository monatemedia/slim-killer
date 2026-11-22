<?php

declare(strict_types=1);

/*
 * The visit counter's front door. visits.js sends each event here; it is recorded
 * and answered with an empty 204. No session, no cookie, nothing sent back.
 *
 * It must never trouble a visitor: if anything goes wrong it is logged, and the
 * answer is still an empty 204.
 */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    http_response_code(204);
    exit;
}

$root = dirname(dirname(__DIR__));

try {
    require $root . '/vendor/autoload.php';

    /** @var Psr\Container\ContainerInterface $container */
    $container = require $root . '/bootstrap/container.php';
    $body = (string) file_get_contents('php://input', false, null, 0, SlimKiller\Visits\VisitCounter::MAX_BYTES + 1);
    $status = $container->get(SlimKiller\Visits\VisitCounter::class)
        ->count($_SERVER, $body, $container->get(SlimKiller\Support\Clock::class)->now());
} catch (Throwable $e) {
    error_log('Slim Killer visit counter: ' . $e->getMessage());
    $status = 204;
}

http_response_code($status);
