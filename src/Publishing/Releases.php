<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

use RuntimeException;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Folder;
use SlimKiller\Support\Paths;

/**
 * The built sites on the host, and which one is live.
 *
 *   public_html/_site/<id>/      one folder per release
 *   public_html/_site/.htaccess  names the live release; replaced in one step to switch
 *   storage/releases.json        the releases, newest first, and which one is live
 *
 * Switching writes a new .htaccess beside the old one and renames it into place (spike
 * S2: LiteSpeed honours it at once). No symlinks, which shared hosts often disable.
 */
final class Releases
{
    public const string FOLDER = '_site';

    public function __construct(
        /** The folder the website is served from; empty until WEB_ROOT is set. */
        private readonly string $webRoot,
        private readonly string $logFile,
    ) {}

    public static function from(AppConfig $config, Paths $paths): self
    {
        $webRoot = $config->webRoot;
        if (str_starts_with($webRoot, '~')) {
            $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');
            $webRoot = $home . substr($webRoot, 1);
        } elseif ($webRoot !== '' && preg_match('#^([A-Za-z]:)?[/\\\\]#', $webRoot) !== 1) {
            $webRoot = $paths->root($webRoot);
        }

        // The real path, without "../", so the .htaccess names the folder plainly.
        $real = $webRoot !== '' ? realpath($webRoot) : false;

        return new self(rtrim($real !== false ? $real : $webRoot, '/\\'), $paths->storage('releases.json'));
    }

    public function webRoot(): string
    {
        return $this->webRoot;
    }

    /** What is missing before releases can be made, or null when nothing is. */
    public function problem(): ?string
    {
        if ($this->webRoot === '') {
            return 'Publishing is not set up: WEB_ROOT in .env is empty. Set it to the folder the website is served from.';
        }
        if (! is_dir($this->webRoot) || ! is_writable($this->webRoot)) {
            return "WEB_ROOT is {$this->webRoot}, which does not exist or is not writable.";
        }

        return null;
    }

    public function folder(string $name = ''): string
    {
        $base = $this->webRoot . '/' . self::FOLDER;

        return $name === '' ? $base : $base . '/' . $name;
    }

    /** @return list<Release> newest first */
    public function all(): array
    {
        $releases = [];
        foreach ($this->log()['releases'] as $data) {
            $release = is_array($data) ? Release::fromArray($data) : null;
            if ($release !== null) {
                $releases[] = $release;
            }
        }

        return $releases;
    }

    public function live(): ?Release
    {
        $live = $this->log()['live'];
        foreach ($this->all() as $release) {
            if ($release->id === $live) {
                return $release;
            }
        }

        return null;
    }

    /** True when this build was fetched before, live or not (so a rollback stays put). */
    public function knows(string $build): bool
    {
        foreach ($this->all() as $release) {
            if ($release->build === $build) {
                return true;
            }
        }

        return false;
    }

    /** The release fetched before the live one, to go back to. */
    public function previous(): ?Release
    {
        $live = $this->live();
        $all = $this->all();
        foreach ($all as $index => $release) {
            if ($live !== null && $release->id === $live->id) {
                return $all[$index + 1] ?? null;
            }
        }

        return null;
    }

    /** Moves an unpacked build into its release folder and records it (not live yet). */
    public function add(Release $release, string $unpacked): void
    {
        $folder = $this->folder($release->id);
        Folder::delete($folder);
        Folder::move($unpacked, $folder);

        $log = $this->log();
        $log['releases'] = array_values(array_filter(
            $log['releases'],
            static fn(mixed $data): bool => ! is_array($data) || ($data['id'] ?? null) !== $release->id,
        ));
        array_unshift($log['releases'], $release->toArray());
        $this->writeLog($log);
    }

    public function switchTo(Release $release): void
    {
        if (! is_dir($this->folder($release->id))) {
            throw new RuntimeException("The release folder {$this->folder($release->id)} is missing.");
        }
        $this->writeAtomically($this->folder('.htaccess'), $this->htaccess($release));

        $log = $this->log();
        $log['live'] = $release->id;
        $this->writeLog($log);
    }

    /**
     * Deletes all but the newest releases. Never the live one.
     *
     * @return list<string> the ids removed
     */
    public function prune(int $keep): array
    {
        $live = $this->live();
        $kept = [];
        $removed = [];
        foreach ($this->all() as $release) {
            if (count($kept) < $keep || ($live !== null && $release->id === $live->id)) {
                $kept[] = $release->toArray();

                continue;
            }
            Folder::delete($this->folder($release->id));
            $removed[] = $release->id;
        }
        $log = $this->log();
        $log['releases'] = $kept;
        $this->writeLog($log);

        return $removed;
    }

    private function htaccess(Release $release): string
    {
        $id = $release->id;
        $folder = str_replace('\\', '/', $this->folder($id));

        return implode("\n", [
            '# Written by Slim Killer (hammer deploy:fetch). It names the live release:',
            "# the build of {$release->shortCommit()}, fetched {$release->fetchedAt}. Do not edit.",
            'Options -Indexes',
            'DirectorySlash Off',
            'RewriteEngine On',
            '',
            '# Already inside the live release: serve the file.',
            "RewriteRule ^{$id}/ - [L]",
            '',
            '# A page asked for without its trailing slash: add it, as the site\'s links do.',
            "RewriteCond {$folder}/\$1 -d",
            'RewriteRule ^(.*[^/])$ /$1/ [R=301,L]',
            '',
            '# Everything else comes from the live release.',
            "RewriteRule ^(.*)$ {$id}/\$1 [L]",
            '',
            "ErrorDocument 404 /" . self::FOLDER . "/{$id}/404.html",
            '',
        ]);
    }

    /** @return array{live: string|null, releases: array<mixed>} */
    private function log(): array
    {
        $data = is_file($this->logFile) ? json_decode((string) file_get_contents($this->logFile), true) : null;
        $data = is_array($data) ? $data : [];

        return [
            'live' => is_string($data['live'] ?? null) ? $data['live'] : null,
            'releases' => is_array($data['releases'] ?? null) ? $data['releases'] : [],
        ];
    }

    /** @param array{live: string|null, releases: array<mixed>} $log */
    private function writeLog(array $log): void
    {
        $this->writeAtomically($this->logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    private function writeAtomically(string $file, string $contents): void
    {
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        $temporary = $file . '.writing';
        if (@file_put_contents($temporary, $contents) === false || ! @rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException("Could not write {$file}.");
        }
    }
}
