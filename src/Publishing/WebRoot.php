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
 *   WEB_ROOT/cp/visit.php    the same for the visit counter's front door
 *   WEB_ROOT/cp/assets/      the panel's CSS, JS and images, copied from the project,
 *                            with an .htaccess that keeps the website's own scripts fresh
 *   WEB_ROOT/cp/not-found.php  answers missing addresses with the 404 page and status
 *   WEB_ROOT/_site/          the releases (written by deploy:fetch)
 *   WEB_ROOT/404.html        a copy of the live release's 404 page (see Releases)
 *
 * Safe to run again: it rewrites its own files and copies the assets afresh (do that
 * after updating Slim Killer). An .htaccess it did not write is kept as a backup.
 */
final class WebRoot
{
    /** Files in cp/assets that the website's own pages load, at a fixed address. */
    public const array WEBSITE_FILES = ['visits.js', 'editor-bar.js', 'editor-bar.css'];

    /** How long a CDN or browser may keep them: five minutes. */
    public const int WEBSITE_FILES_SECONDS = 300;

    public const string MARKER = 'Written by Slim Killer (hammer web:install)';

    /** Put into the 404 page by cp/not-found.php, for visits.js to find. */
    public const string NOT_FOUND_MARK = '<meta name="slim-killer" content="not-found">';

    public function __construct(
        private readonly Releases $releases,
        private readonly Paths $paths,
        /** HIDE_FROM_SEARCH in .env: a test copy, kept out of search results. */
        private readonly bool $hideFromSearch = false,
        /** APP_URL in .env: the website's one address; the other www-form moves there. */
        private readonly string $siteUrl = '',
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
        if ($this->hideFromSearch) {
            $done[] = 'Search engines are asked not to list this website (HIDE_FROM_SEARCH in .env).';
        }

        $entry = $this->paths->root('public/cp/index.php');
        $this->write($root . '/cp/index.php', implode("\n", [
            '<?php',
            '',
            '// ' . self::MARKER . '. Loads the control panel from outside the web root.',
            'require ' . var_export($entry, true) . ';',
            '',
        ]));
        $done[] = "Wrote cp/index.php, which loads the control panel from {$this->paths->root()}.";

        $this->write($root . '/cp/visit.php', implode("\n", [
            '<?php',
            '',
            '// ' . self::MARKER . '. Loads the visit counter from outside the web root.',
            'require ' . var_export($this->paths->root('public/cp/visit.php'), true) . ';',
            '',
        ]));
        $done[] = 'Wrote cp/visit.php, where the website\'s visits.js sends what it counts.';

        Folder::copy($this->paths->root('public/cp/assets'), $root . '/cp/assets');
        $this->write($root . '/cp/assets/.htaccess', $this->assetsHtaccess());
        $done[] = "Copied the control panel's assets to cp/assets.";

        $this->write($root . '/' . Releases::NOT_FOUND, $this->notFound());
        $done[] = 'Wrote ' . Releases::NOT_FOUND . ', which answers missing pages with the site\'s 404 page.';

        $live = $this->releases->live();
        if ($live !== null) {
            $this->releases->switchTo($live);
            $done[] = 'Brought the live release\'s rules and 404 page up to date.';
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
            '# A backstop only: missing pages are answered by cp/not-found.php (see _site/.htaccess).',
            '# 404.html is a copy of the live release\'s 404 page, replaced at every switch.',
            'ErrorDocument 404 /404.html',
            '',
            ...($this->hideFromSearch ? [
                '# HIDE_FROM_SEARCH is on in .env: a test copy, kept out of search results.',
                '<IfModule mod_headers.c>',
                'Header always set X-Robots-Tag "noindex, nofollow"',
                '</IfModule>',
                '',
            ] : []),
            '# Certificate renewals (Let\'s Encrypt) must reach their files.',
            'RewriteRule ^\.well-known/ - [L]',
            '',
            ...$this->oneAddress(),
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

    /**
     * The files the website's pages load (the visit counter and the website bar) keep a
     * fixed address, so without this a CDN or browser keeps an old copy for days after
     * an update (Hostinger's CDN: 7 days). Five minutes is short enough for an update to
     * reach everyone soon, and long enough to save every page view a request. The panel's
     * own files carry their change time in their address, so they can be kept long.
     */
    private function assetsHtaccess(): string
    {
        return implode("\n", [
            '# ' . self::MARKER . '. Run that command again rather than editing.',
            '<IfModule mod_headers.c>',
            '<FilesMatch "^(' . implode('|', array_map(static fn(string $file): string => preg_quote($file, '/'), self::WEBSITE_FILES)) . ')$">',
            'Header set Cache-Control "public, max-age=' . self::WEBSITE_FILES_SECONDS . '"',
            '</FilesMatch>',
            '</IfModule>',
            '',
        ]);
    }

    /**
     * One address for the website, the one in APP_URL: the www- or bare form of it moves
     * there with a permanent redirect. Two addresses split search results and, since a
     * browser keeps storage and sign-ins per address, the website bar would show on one
     * and not the other. Nothing for a local address (.test, localhost) or an IP.
     *
     * @return list<string>
     */
    private function oneAddress(): array
    {
        $parts = parse_url($this->siteUrl);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $scheme = is_array($parts) && ($parts['scheme'] ?? '') === 'http' ? 'http' : 'https';
        if ($host === '' || ! str_contains($host, '.') || str_ends_with($host, '.test')
            || str_starts_with($host, 'localhost') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $other = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;

        return [
            '# One address for the website (APP_URL): ' . $other . ' moves to ' . $host . '.',
            'RewriteCond %{HTTP_HOST} ^' . preg_quote($other, '/') . '$ [NC]',
            'RewriteRule ^(.*)$ ' . $scheme . '://' . $host . '/$1 [R=301,L]',
            '',
        ];
    }

    /** Whether a release is live, so the website itself is already in place. */
    public function hasLiveRelease(): bool
    {
        return $this->releases->live() !== null;
    }

    private function notFound(): string
    {
        return implode("\n", [
            '<?php',
            '',
            '// ' . self::MARKER . '. The live release\'s 404 page, with a 404 status.',
            'http_response_code(404);',
            "header('Content-Type: text/html; charset=utf-8');",
            "header('Cache-Control: no-store');",
            "\$page = dirname(__DIR__) . '/404.html';",
            "\$html = is_file(\$page) ? (string) file_get_contents(\$page) : '<!DOCTYPE html><title>Page not found</title><h1>Page not found</h1>';",
            '// Tells visits.js to count a page not found, rather than a page.',
            "\$mark = '" . self::NOT_FOUND_MARK . "';",
            "\$marked = preg_replace('#</head>#i', \$mark . '\$0', \$html, 1, \$count);",
            'echo is_string($marked) && $count === 1 ? $marked : $html . $mark;',
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
