<?php

declare(strict_types=1);

/*
 * The control panel's front controller.
 *
 * Like hammer, it checks the basics in syntax that any PHP can read, so a website on
 * an old PHP shows a clear message instead of a blank page or a parse error.
 */

if (version_compare(PHP_VERSION, '8.4.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Slim Killer needs PHP 8.4 or newer, and this website runs PHP ' . PHP_VERSION . ".\n"
        . "Choose PHP 8.4 for this website in your hosting control panel.\n";
    exit;
}

$root = dirname(dirname(__DIR__));

if (! is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Slim Killer's dependencies are not installed. Run composer install in " . $root . ".\n";
    exit;
}

require $root . '/bootstrap/cp.php';
