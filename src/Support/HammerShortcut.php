<?php

declare(strict_types=1);

namespace SlimKiller\Support;

/**
 * Lets "hammer doctor" work from any folder, instead of "/opt/alt/php84/usr/bin/php
 * hammer doctor" from the slim-killer folder. Hosts often have several PHPs, and the one
 * a shell finds as "php" isn't always the one Slim Killer needs.
 *
 * `hammer shortcut` writes ~/bin/hammer, a three-line script that runs this server's
 * hammer with the PHP it was started with, and adds ~/bin to the PATH (one marked line,
 * added once) in ~/.bashrc and in the file an SSH sign-in reads: ~/.bash_profile, or
 * ~/.profile on hosts without one. Only the hosting account's own files change.
 */
final readonly class HammerShortcut
{
    public const string MARK = 'Written by Slim Killer (hammer shortcut)';

    public const string PATH_LINE = 'export PATH="$HOME/bin:$PATH"';

    /** Bash reads the first of these that exists when you sign in over SSH. */
    private const array SIGN_IN_FILES = ['.bash_profile', '.bash_login', '.profile'];

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

    /** Is the shortcut there, does it run this Slim Killer, and does signing in find it? */
    public static function installedFor(string $root): bool
    {
        $shortcut = self::forAccount($root);

        return $shortcut !== null && $shortcut->installed() && $shortcut->foundAtSignIn();
    }

    /** The file bash reads when you sign in over SSH (~/.profile when there is none yet). */
    public function signInFile(): string
    {
        foreach (self::SIGN_IN_FILES as $name) {
            if (is_file($this->home . '/' . $name)) {
                return $this->home . '/' . $name;
            }
        }

        return $this->home . '/.profile';
    }

    /** Does signing in over SSH put ~/bin on the PATH, directly or by loading ~/.bashrc? */
    public function foundAtSignIn(): bool
    {
        $signIn = self::read($this->signInFile());

        return str_contains($signIn, self::PATH_LINE)
            || (self::loadsBashrc($signIn) && str_contains(self::read($this->home . '/.bashrc'), self::PATH_LINE));
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

        foreach ($this->pathFiles() as $file) {
            if ($this->addPathLine($file)) {
                $changed[] = "Added ~/bin to the PATH in {$file}.";
            }
        }

        return $changed;
    }

    /**
     * ~/.bashrc for a shell opened inside a session, and the sign-in file for SSH, unless
     * that file already loads ~/.bashrc.
     *
     * @return list<string>
     */
    private function pathFiles(): array
    {
        $bashrc = $this->home . '/.bashrc';
        $signIn = $this->signInFile();

        return self::loadsBashrc(self::read($signIn)) ? [$bashrc] : [$bashrc, $signIn];
    }

    /** Adds the PATH line to the end of a file, once; true when it was added. */
    private function addPathLine(string $file): bool
    {
        $current = self::read($file);
        if (str_contains($current, self::PATH_LINE)) {
            return false;
        }
        $prefix = $current === '' || str_ends_with($current, "\n") ? '' : "\n";
        $line = $prefix . "\n# Added by Slim Killer (hammer shortcut): your own commands, in ~/bin.\n" . self::PATH_LINE . "\n";
        if (file_put_contents($file, $line, FILE_APPEND) === false) {
            throw new \RuntimeException("Could not add ~/bin to the PATH in {$file}.");
        }

        return true;
    }

    /** Does this sign-in file run ~/.bashrc (". ~/.bashrc" or "source $HOME/.bashrc")? */
    private static function loadsBashrc(string $text): bool
    {
        return preg_match('#^\s*(\.|source)\s+["\']?(~|\$HOME|\$\{HOME\})/\.bashrc\b#m', $text) === 1;
    }

    private static function read(string $file): string
    {
        return is_file($file) ? (string) file_get_contents($file) : '';
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
