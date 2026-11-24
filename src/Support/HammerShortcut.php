<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Lets "hammer doctor" work from any folder, instead of "/opt/alt/php84/usr/bin/php
 * hammer doctor" from the slim-killer folder. Hosts often have several PHPs, and the one
 * a shell finds as "php" isn't always the one Slim Killer needs.
 *
 * `hammer shortcut` writes ~/bin/hammer, a three-line script that runs this server's
 * hammer with the PHP it was started with, and adds ~/bin to the PATH in ~/.bashrc (one
 * marked line, added once). Only the hosting account's own files change.
 */
final readonly class HammerShortcut
{
    public const string MARK = 'Written by Slim Killer (hammer shortcut)';

    public const string PATH_LINE = 'export PATH="$HOME/bin:$PATH"';

    public function __construct(
        private string $home,
        private string $root,
    ) {}

    /** The shortcut for the account this Slim Killer runs under; null when its home folder is unknown. */
    public static function forAccount(string $root): ?self
    {
        foreach (['HOME', 'USERPROFILE'] as $name) {
            $home = getenv($name);
            if (is_string($home) && $home !== '') {
                return new self(rtrim($home, '/\\'), $root);
            }
        }
        if (preg_match('#^(/home/[^/]+)/#', $root . '/', $match) === 1) {
            return new self($match[1], $root);
        }

        return null;
    }

    /** Is the shortcut there, and does it run this Slim Killer? */
    public static function installedFor(string $root): bool
    {
        return self::forAccount($root)?->installed() ?? false;
    }

    public function script(): string
    {
        return $this->home . '/bin/hammer';
    }

    public function installed(): bool
    {
        $script = $this->script();

        return is_file($script) && str_contains((string) file_get_contents($script), $this->hammer());
    }

    /**
     * Writes the shortcut for this PHP, and puts ~/bin on the PATH.
     *
     * @return list<string> what changed, in a line each; empty when nothing needed to
     */
    public function install(string $php): array
    {
        $changed = [];
        $script = $this->script();
        $contents = implode("\n", [
            '#!/bin/sh',
            '# ' . self::MARK . ': runs this server\'s hammer with the PHP it needs,',
            '# from any folder. Run "hammer shortcut" again after changing PHP.',
            'exec ' . self::quote($php) . ' ' . self::quote($this->hammer()) . ' "$@"',
            '',
        ]);
        if (! is_file($script) || file_get_contents($script) !== $contents) {
            if (! is_dir(dirname($script)) && ! mkdir(dirname($script), 0755, true) && ! is_dir(dirname($script))) {
                throw new \RuntimeException('Could not make ' . dirname($script) . '.');
            }
            if (file_put_contents($script, $contents) === false) {
                throw new \RuntimeException("Could not write {$script}.");
            }
            chmod($script, 0755);
            $changed[] = "Wrote {$script}, which runs {$this->hammer()} with {$php}.";
        }

        $bashrc = $this->home . '/.bashrc';
        $current = is_file($bashrc) ? (string) file_get_contents($bashrc) : '';
        if (! str_contains($current, self::PATH_LINE)) {
            $prefix = $current === '' || str_ends_with($current, "\n") ? '' : "\n";
            $line = $prefix . "\n# Added by Slim Killer (hammer shortcut): your own commands, in ~/bin.\n" . self::PATH_LINE . "\n";
            if (file_put_contents($bashrc, $line, FILE_APPEND) === false) {
                throw new \RuntimeException("Could not add ~/bin to the PATH in {$bashrc}.");
            }
            $changed[] = "Added ~/bin to the PATH in {$bashrc}.";
        }

        return $changed;
    }

    /**
     * Quoted for sh, the shell the script runs in, whatever computer writes it: PHP's
     * escapeshellarg() quotes for Windows' own command line when it runs on Windows.
     */
    private static function quote(string $text): string
    {
        return "'" . str_replace("'", "'\\''", $text) . "'";
    }

    private function hammer(): string
    {
        return rtrim($this->root, '/\\') . '/hammer';
    }
}
