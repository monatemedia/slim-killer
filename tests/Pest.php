<?php

declare(strict_types=1);

use SlimKiller\Doctor\Environment\SnapshotEnvironment;
use SlimKiller\Doctor\Requirements;

/*
 * A healthy PHP environment for tests. Override only what a test is about:
 *
 *     environment(['php_version' => '8.3.36'])
 */
function environment(array $overrides = [], string $label = 'Command line'): SnapshotEnvironment
{
    $defaults = [
        'php_version' => '8.4.27',
        'sapi' => 'cli',
        'php_binary' => '/opt/alt/php84/usr/bin/php',
        'extensions' => [...array_keys(Requirements::REQUIRED_EXTENSIONS), ...array_keys(Requirements::RECOMMENDED_EXTENSIONS)],
        'ini' => [
            'memory_limit' => '512M',
            'max_execution_time' => '0',
            'upload_max_filesize' => '128M',
            'post_max_size' => '128M',
        ],
        'disabled_functions' => [],
        'webp_write' => true,
        'avif' => false,
    ];

    return SnapshotEnvironment::fromProbe(array_replace($defaults, $overrides), $label);
}

/** A fresh, empty temporary folder, removed again at the end of the test run. */
function temporaryFolder(): string
{
    $folder = sys_get_temp_dir() . '/slim-killer-test-' . bin2hex(random_bytes(6));
    mkdir($folder, 0755, true);
    register_shutdown_function(static function () use ($folder): void {
        if (! is_dir($folder)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($folder);
    });

    return $folder;
}

/** @return list<string> */
function statuses(array $results): array
{
    return array_map(static fn($result) => $result->status->value, $results);
}
