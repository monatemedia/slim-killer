<?php
declare(strict_types=1);

/**
 * Slim Killer Environment Bootstrapper
 * Parses raw local configuration tokens into global env scopes.
 */
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip blank lines and plain comments
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        // Split out environment tokens cleanly
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            
            $name = trim($name);
            $value = trim($value);
            
            // Strip structural wrapper quotes from strings
            if (preg_match('/^"(.+)"$/', $value, $matches)) {
                $value = $matches[1];
            }

            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}