<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

use RuntimeException;
use SlimKiller\Support\Folder;
use SlimKiller\Support\Paths;

/**
 * Sets up the folder the website is served from (WEB_ROOT), so Slim Killer's own code
 * stays outside it:
 *
 *   WEB_ROOT/.htaccess       /cp to the control panel, everything else to the live release
 *   WEB_ROOT/cp/index.php    a two-line stub that loads the panel from the project folder
 *   WEB_ROOT/cp/assets/      the panel's CSS, JS and images, copied from the project
 *   WEB_ROOT/_site/          the releases (written by deploy:fetch)
 *   WEB_ROOT/404.html        a copy of the live release's 404 page (see Releases)
 *
 * Safe to run again: it rewrites its own files and copies the assets afresh (do that
 * after updating Slim Killer). An .htaccess it did not write is kept as a backup.
 */
final class WebRoot
{
    public const string MARKER = 'Written by Slim Killer (hammer web:install)';

    public function __construct(
        private readonly Releases $releases,
        private readonly Paths $paths,
    ) {}

    /**
     * @return list<string> what was done, in the order it was done
     */
    public function install(): array
    {
        $problem = $this->releases->problem();
        if ($problem !== null) {
            throw new RuntimeException($problem);
        }
        $root = $this->releases->webRoot();
        $done = [];

        $htaccess = $root . '/.htaccess';
        if (is_file($htaccess) && ! str_contains((string) file_get_contents($htaccess), self::MARKER)) {
            $backup = $root . '/.htaccess.before-slim-killer';
            if (file_exists($backup)) {
                $backup .= '-' . bin2hex(random_bytes(3));
            }
            if (! @rename($htaccess, $backup)) {
                throw new RuntimeException("Could not move the existing {$htaccess} aside.");
            }
            $done[] = 'Kept the existing .htaccess as ' . basename($backup) . '.';
        }
        $this->write($htaccess, $this->htaccess());
        $done[] = 'Wrote .htaccess: /cp goes to the control panel, everything else to the live release.';

        $entry = $this->paths->root('public/cp/index.php');
        $this->write($root . '/cp/index.php', implode("\n", [
            '<?php',
            '',
            '// ' . self::MARKER . '. Loads the control panel from outside the web root.',
            'require ' . var_export($entry, true) . ';',
            '',
        ]));
        $done[] = "Wrote cp/index.php, which loads the control panel from {$this->paths->root()}.";

        Folder::copy($this->paths->root('public/cp/assets'), $root . '/cp/assets');
        $done[] = "Copied the control panel's assets to cp/assets.";

        $live = $this->releases->live();
        if ($live !== null) {
            $this->releases->placeErrorPage($live);
            $done[] = 'Placed the live release\'s 404 page.';
        }

        return $done;
    }

    private function htaccess(): string
    {
        return implode("\n", [
            '# ' . self::MARKER . '. Run that command again rather than editing.',
            'Options -Indexes',
            'DirectoryIndex index.html index.php',
            'RewriteEngine On',
            '',
            '# The site\'s own 404 page: a copy of the live release\'s, replaced at every switch.',
            '# Named here because a server picks the error page by the address the visitor asked',
            '# for; a real file because LiteSpeed does not route error pages through rewrites.',
            'ErrorDocument 404 /404.html',
            '',
            '# Certificate renewals (Let\'s Encrypt) must reach their files.',
            'RewriteRule ^\.well-known/ - [L]',
            '',
            '# The control panel: its real files (assets) as they are, everything else to its',
            '# front controller.',
            'RewriteCond %{REQUEST_FILENAME} -f',
            'RewriteRule ^cp/ - [L]',
            'RewriteRule ^cp(/.*)?$ cp/index.php [L]',
            '',
            '# Everything else comes from the live release (see _site/.htaccess).',
            'RewriteCond %{REQUEST_URI} !^/' . Releases::FOLDER . '/',
            'RewriteRule ^(.*)$ ' . Releases::FOLDER . '/$1 [L]',
            '',
        ]);
    }

    private function write(string $file, string $contents): void
    {
        if (! is_dir(dirname($file)) && ! @mkdir(dirname($file), 0755, true)) {
            throw new RuntimeException('Could not create ' . dirname($file) . '.');
        }
        $temporary = $file . '.writing';
        if (@file_put_contents($temporary, $contents) === false || ! @rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException("Could not write {$file}.");
        }
    }
}
