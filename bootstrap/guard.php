<?php

/*
 * PRE-FLIGHT GUARD for `hammer` and `artisan`. This file is deliberately written in old
 * PHP syntax (it parses on PHP 5.3 and newer) so that it can always run far enough to
 * explain itself. Shared hosts often start the command line with an older PHP than the
 * website uses (on Hostinger the default is 8.3 while websites run 8.4). Everything
 * after the guard lives in bootstrap/hammer.php and uses modern PHP.
 *
 * It is a separate file because PHP prints the "#!/usr/bin/env php" line of any file
 * it requires, so `artisan` cannot simply require `hammer`.
 */

$required = '8.4.0';

if (version_compare(PHP_VERSION, $required, '<')) {
    $found = array();
    $patterns = array(
        '/opt/alt/php8*/usr/bin/php',
        '/opt/cpanel/ea-php8*/root/usr/bin/php',
        '/usr/local/bin/php8*',
        '/usr/bin/php8*',
    );
    foreach ($patterns as $pattern) {
        $matches = glob($pattern);
        if (is_array($matches)) {
            foreach ($matches as $binary) {
                if (preg_match('/php-?(8)\.?([0-9])/', $binary, $m) && version_compare($m[1] . '.' . $m[2] . '.0', $required, '>=') && is_executable($binary)) {
                    $found[] = $binary;
                }
            }
        }
    }

    fwrite(STDERR, "\nSlim Killer needs PHP {$required} or newer. This is PHP " . PHP_VERSION . " (" . PHP_BINARY . ").\n");
    if (count($found) > 0) {
        fwrite(STDERR, "\nThis server has a newer PHP. Run hammer with it, for example:\n\n");
        fwrite(STDERR, "    " . $found[0] . " " . $argv[0] . (count($argv) > 1 ? ' ' . implode(' ', array_slice($argv, 1)) : ' doctor') . "\n");
        if (count($found) > 1) {
            fwrite(STDERR, "\nOther suitable versions found:\n    " . implode("\n    ", array_slice($found, 1)) . "\n");
        }
    } else {
        fwrite(STDERR, "\nNo newer PHP was found in the usual places. Ask your host how to run PHP {$required} on the command line.\n");
    }
    fwrite(STDERR, "\n");
    exit(1);
}

if (! is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    fwrite(STDERR, "\nDependencies are missing. Run `composer install` in " . dirname(__DIR__) . " first.\n\n");
    exit(1);
}
