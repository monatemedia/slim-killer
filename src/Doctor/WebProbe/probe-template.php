<?php
/*
 * Slim Killer web probe. Placed in the web root for a few seconds by `hammer doctor`,
 * which reads it and then deletes the whole folder. It reports facts about the
 * website's PHP and nothing else, and only to a request carrying the right key.
 *
 * Written in old PHP syntax on purpose: it runs on whatever PHP the website has, which
 * may be older than Slim Killer supports, and it must still report that clearly.
 */

$key = '__PROBE_KEY__';
$given = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($given === '' || ! function_exists('hash_equals') || ! hash_equals($key, $given)) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$disabled = array();
foreach (explode(',', (string) ini_get('disable_functions')) as $name) {
    $name = trim($name);
    if ($name !== '') {
        $disabled[] = $name;
    }
}

$webp = false;
if (function_exists('imagecreatetruecolor') && function_exists('imagewebp')) {
    $image = imagecreatetruecolor(64, 64);
    ob_start();
    $ok = imagewebp($image, null, 75);
    $bytes = ob_get_clean();
    $webp = $ok && $bytes !== '';
}

$gd = function_exists('gd_info') ? gd_info() : array();

echo json_encode(array(
    'probe' => 'slim-killer',
    'rewrite' => isset($_GET['rewrite']),
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'php_binary' => defined('PHP_BINARY') ? PHP_BINARY : '',
    'extensions' => array_map('strtolower', get_loaded_extensions()),
    'ini' => array(
        'memory_limit' => (string) ini_get('memory_limit'),
        'max_execution_time' => (string) ini_get('max_execution_time'),
        'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
        'post_max_size' => (string) ini_get('post_max_size'),
    ),
    'disabled_functions' => $disabled,
    'webp_write' => $webp,
    'avif' => ! empty($gd['AVIF Support']),
));
