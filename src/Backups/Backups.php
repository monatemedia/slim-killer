<?php

declare(strict_types=1);

namespace SlimKiller\Backups;

use DateTimeImmutable;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\Folder;
use SlimKiller\Support\Hammer;
use SlimKiller\Support\Paths;
use SlimKiller\Visits\SiteTime;

/**
 * Backups as a whole: one made on this server, then encrypted and sent to the site's
 * Google Drive, which keeps the last BACKUPS_KEPT. Cron calls daily(); the hammer backup commands
 * call the rest.
 *
 * The last backup is noted in storage/backups/last.json, so cron makes one a day, and
 * one that did not reach Google Drive is sent again the next hour rather than made again.
 */
final readonly class Backups
{
    public const string LAST = 'backups/last.json';

    public function __construct(
        private DatabaseBackup $database,
        private GoogleDrive $drive,
        private BackupEncryption $encryption,
        private AppConfig $config,
        private SiteTime $time,
        private Paths $paths,
    ) {}

    /**
     * Makes a backup now and sends it to Google Drive, and says so in one line.
     *
     * @throws BackupFailed
     */
    public function backUp(DateTimeImmutable $now): string
    {
        $path = $this->database->make($now);
        $this->note($this->time->day($now), basename($path), false);

        return $this->send($path, $this->time->day($now));
    }

    /**
     * Cron's daily backup: one a day, sent again if it has not reached Google Drive.
     *
     * @throws BackupFailed
     */
    public function daily(DateTimeImmutable $now): string
    {
        $today = $this->time->day($now);
        $last = $this->last();
        if ($last !== null && $last['day'] === $today) {
            if ($last['sent'] || ! $this->drive->isConnected()) {
                return "Already backed up today ({$last['file']}).";
            }
            $path = $this->database->pathHere($last['file']);
            if ($path !== null) {
                return $this->send($path, $today);
            }
        }

        return $this->backUp($now);
    }

    /**
     * Restores the newest backup into a temporary copy and says what is in it: from
     * Google Drive when it is connected, since that is the copy a disaster leaves.
     *
     * @throws BackupFailed
     */
    public function checkNewest(): string
    {
        if (! $this->drive->isConnected()) {
            $newest = $this->database->newestHere() ?? throw new BackupFailed('There is no backup yet. Run ' . Hammer::command('backup') . ' to make one.');

            return 'Restored ' . basename($newest) . ' from this server into a temporary copy: '
                . $this->database->check($newest) . '. (Google Drive is not connected, so it was not checked.)';
        }

        $newest = $this->drive->backups()[0] ?? throw new BackupFailed('There is no backup on Google Drive yet. Run ' . Hammer::command('backup') . ' to make one.');
        $backup = $this->fromDrive($newest);
        try {
            $summary = $this->database->check($backup);
        } finally {
            Folder::removeFile($backup);
        }

        return "Restored {$newest['name']} from Google Drive into a temporary copy: {$summary}.";
    }

    /**
     * Puts a backup back as the live database: from this server if it is here, otherwise
     * from Google Drive.
     *
     * @return array{summary: string, before: string, from: string}
     *
     * @throws BackupFailed
     */
    public function restore(string $name, DateTimeImmutable $now): array
    {
        if (preg_match(DatabaseBackup::NAME_ON_DRIVE, $name) !== 1) {
            throw new BackupFailed("\"{$name}\" is not a backup's name. They look like backup-2026-11-24-0215.sqlite.gz.");
        }

        $plain = str_ends_with($name, BackupEncryption::SUFFIX) ? substr($name, 0, -strlen(BackupEncryption::SUFFIX)) : $name;
        $here = $this->database->pathHere($plain);
        if ($here !== null) {
            return $this->database->restore($here, $now) + ['from' => 'this server'];
        }
        if (! $this->drive->isConnected()) {
            throw new BackupFailed("There is no {$name} on this server, and Google Drive is not connected.");
        }
        $found = array_values(array_filter(
            $this->drive->backups(),
            static fn(array $file): bool => in_array($file['name'], [$plain, $plain . BackupEncryption::SUFFIX], true),
        ));
        if ($found === []) {
            throw new BackupFailed("There is no {$plain} on this server or on Google Drive.");
        }

        $backup = $this->fromDrive($found[0]);
        try {
            return $this->database->restore($backup, $now) + ['from' => 'Google Drive'];
        } finally {
            Folder::removeFile($backup);
        }
    }

    /**
     * The backups there are, newest first: on this server, and on Google Drive when it
     * is connected.
     *
     * @return array{here: list<string>, drive: list<string>|null}
     *
     * @throws BackupFailed
     */
    public function names(): array
    {
        return [
            'here' => array_map(basename(...), $this->database->here()),
            'drive' => $this->drive->isConnected() ? array_column($this->drive->backups(), 'name') : null,
        ];
    }

    /** @return array{day: string, file: string, sent: bool}|null the last backup cron or hammer made */
    public function last(): ?array
    {
        $file = $this->paths->storage(self::LAST);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data) || ! is_string($data['day'] ?? null) || ! is_string($data['file'] ?? null)) {
            return null;
        }

        return ['day' => $data['day'], 'file' => $data['file'], 'sent' => ($data['sent'] ?? false) === true];
    }

    private function send(string $path, string $day): string
    {
        $name = basename($path);
        if (! $this->drive->isConnected()) {
            return "Saved {$name} on this server only: Google Drive is not connected (" . Hammer::command('backup:connect') . ').';
        }

        $locked = $this->temporary($name . BackupEncryption::SUFFIX);
        try {
            file_put_contents($locked, $this->encryption->lock((string) file_get_contents($path)));
            $this->drive->upload($locked);
            $removed = $this->drive->keepNewest($this->config->backupsKept);
        } catch (BackupFailed $problem) {
            throw new BackupFailed("Saved {$name} on this server, but not on Google Drive: " . $problem->getMessage() . ' Cron tries again within the hour.', 0, $problem);
        } finally {
            Folder::removeFile($locked);
        }
        $this->note($day, $name, true);

        return "Saved {$name} here and, encrypted, on Google Drive"
            . ($removed > 0 ? ", which keeps {$this->config->backupsKept}: removed the {$removed} oldest" : '') . '.';
    }

    /**
     * Downloads a backup from Google Drive and decrypts it, into a temporary file the
     * caller removes.
     *
     * @param array{id: string, name: string} $file
     *
     * @throws BackupFailed
     */
    private function fromDrive(array $file): string
    {
        $download = $this->temporary($file['name']);
        $this->drive->download($file['id'], $download);
        if (! str_ends_with($file['name'], BackupEncryption::SUFFIX)) {
            return $download;
        }

        $plain = $this->temporary(substr($file['name'], 0, -strlen(BackupEncryption::SUFFIX)));
        try {
            file_put_contents($plain, $this->encryption->unlock((string) file_get_contents($download)));
        } finally {
            Folder::removeFile($download);
        }

        return $plain;
    }

    private function temporary(string $name): string
    {
        $folder = $this->paths->storage('tmp');
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        return $folder . '/' . $name;
    }

    private function note(string $day, string $file, bool $sent): void
    {
        @file_put_contents(
            $this->paths->storage(self::LAST),
            json_encode(['day' => $day, 'file' => $file, 'sent' => $sent], JSON_THROW_ON_ERROR),
            LOCK_EX,
        );
    }
}
