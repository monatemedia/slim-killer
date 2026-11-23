<?php

declare(strict_types=1);

namespace SlimKiller\Backups;

use SlimKiller\Support\Paths;

/**
 * What Slim Killer keeps about the site's Google Drive, in storage/google-drive.json
 * (outside the web root, readable only by the site's own user):
 *
 * - the refresh token Google gave when the site's owner allowed it, which is a secret;
 * - the backup folder's id, so it is looked up once;
 * - while connecting, the one-time values of the link that was printed.
 */
final readonly class DriveStore
{
    public const string FILE = 'google-drive.json';

    public function __construct(
        private Paths $paths,
    ) {}

    public function refreshToken(): string
    {
        return $this->text('refresh_token');
    }

    /** The folder's id, if it was found under this name; empty otherwise. */
    public function folderId(string $name): string
    {
        return $this->text('folder_name') === $name ? $this->text('folder_id') : '';
    }

    public function rememberFolder(string $name, string $id): void
    {
        $this->write(['folder_name' => $name, 'folder_id' => $id] + $this->read());
    }

    public function forgetFolder(): void
    {
        $data = $this->read();
        unset($data['folder_name'], $data['folder_id']);
        $this->write($data);
    }

    /** The link was printed. Backups carry on with the old connection until the new one works. */
    public function startConnecting(string $verifier, string $state): void
    {
        $this->write(['connecting' => ['verifier' => $verifier, 'state' => $state]] + $this->read());
    }

    /** @return array{verifier: string, state: string}|null */
    public function connecting(): ?array
    {
        $pending = $this->read()['connecting'] ?? null;
        if (! is_array($pending) || ! is_string($pending['verifier'] ?? null) || ! is_string($pending['state'] ?? null)) {
            return null;
        }

        return ['verifier' => $pending['verifier'], 'state' => $pending['state']];
    }

    /** Connected: the new token replaces everything, and the folder is looked up again. */
    public function connected(string $refreshToken): void
    {
        $this->write(['refresh_token' => $refreshToken]);
    }

    private function text(string $key): string
    {
        $value = $this->read()[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $file = $this->paths->storage(self::FILE);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        $file = $this->paths->storage(self::FILE);
        if (file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new BackupFailed("Could not write {$file}. Is the storage folder writable?");
        }
        @chmod($file, 0600);
    }
}
