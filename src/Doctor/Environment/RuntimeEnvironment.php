<?php

declare(strict_types=1);

namespace SlimKiller\Doctor\Environment;

/**
 * The PHP that is running right now (the command line, when hammer runs).
 */
final class RuntimeEnvironment implements Environment
{
    public function label(): string
    {
        return PHP_SAPI === 'cli' ? 'Command line' : 'Website';
    }

    public function isCommandLine(): bool
    {
        return PHP_SAPI === 'cli';
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function sapi(): string
    {
        return PHP_SAPI;
    }

    public function phpBinary(): string
    {
        return PHP_BINARY;
    }

    public function hasExtension(string $name): bool
    {
        return extension_loaded($name);
    }

    public function ini(string $key): ?string
    {
        $value = ini_get($key);

        return $value === false ? null : $value;
    }

    public function disabledFunctions(): array
    {
        $raw = (string) ini_get('disable_functions');

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $f): bool => $f !== ''));
    }

    public function canWriteWebp(): bool
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatetruecolor')) {
            return false;
        }

        $image = imagecreatetruecolor(64, 64);
        if ($image === false) {
            return false;
        }

        ob_start();
        $written = imagewebp($image, null, 75);
        $bytes = (string) ob_get_clean();

        return $written && $bytes !== '';
    }

    public function supportsAvif(): bool
    {
        return function_exists('gd_info') && ! empty(gd_info()['AVIF Support']);
    }
}
