<?php

declare(strict_types=1);

namespace SlimKiller\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * The few folder operations PHP leaves out: delete a whole tree, and move one even when
 * rename() cannot (between two disks, on Windows).
 */
final class Folder
{
    public static function delete(string $folder): void
    {
        if (! is_dir($folder)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            assert($item instanceof \SplFileInfo);
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($folder);
    }

    public static function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }
        self::copy($from, $to);
        self::delete($from);
    }

    /** Puts $new where $target is, then deletes the old one, so $target is never missing for long. */
    public static function replace(string $target, string $new): void
    {
        $old = $target . '.old-' . bin2hex(random_bytes(4));
        if (is_dir($target)) {
            self::move($target, $old);
        }
        self::move($new, $target);
        self::delete($old);
    }

    private static function copy(string $from, string $to): void
    {
        if (! is_dir($to) && ! @mkdir($to, 0755, true)) {
            throw new RuntimeException("Could not create {$to}");
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            assert($item instanceof \SplFileInfo);
            $path = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
            if ($item->isDir()) {
                @mkdir($path, 0755, true);
            } elseif (! @copy($item->getPathname(), $path)) {
                throw new RuntimeException("Could not copy {$item->getPathname()}");
            }
        }
    }
}
