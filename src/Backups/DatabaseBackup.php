<?php

declare(strict_types=1);

namespace SlimKiller\Backups;

use DateTimeImmutable;
use PDO;
use PDOException;
use SlimKiller\Database\Database;
use SlimKiller\Support\Folder;
use SlimKiller\Support\Paths;
use SlimKiller\Visits\SiteTime;

/**
 * Backs up the control panel's database (people, sign-in records, visits) into
 * storage/backups/, and checks a backup by restoring it into a temporary copy. The
 * website's content is not in it: that is in the site's GitHub repository already.
 *
 * - The copy is made by SQLite itself (VACUUM INTO), never by copying the file, so a
 *   visit counted halfway through cannot leave a damaged copy.
 * - Each copy is checked (PRAGMA integrity_check) before it is kept, then gzipped:
 *   backup-2026-11-24-0215.sqlite.gz, in the site's time, so the names sort by date.
 * - This server keeps the last 7. Google Drive keeps the long history.
 *
 * The live database is opened only while it is copied or replaced, so a restore can
 * swap the file with nothing holding it open.
 */
final readonly class DatabaseBackup
{
    public const string FOLDER = 'backups';

    public const int KEPT_HERE = 7;

    /** A backup's name; anything else in the folder is left alone. */
    public const string NAME = '/^backup-\d{4}-\d{2}-\d{2}-\d{4}\.sqlite\.gz$/';

    /** A backup's name on Google Drive, where it is encrypted (.enc on the end). */
    public const string NAME_ON_DRIVE = '/^backup-\d{4}-\d{2}-\d{2}-\d{4}\.sqlite\.gz(\.enc)?$/';

    public function __construct(
        private Paths $paths,
        private SiteTime $time,
    ) {}

    /**
     * Makes a checked backup now and returns its path.
     *
     * @throws BackupFailed
     */
    public function make(DateTimeImmutable $now): string
    {
        $folder = $this->folder();
        $name = 'backup-' . $now->setTimezone($this->time->zone)->format('Y-m-d-Hi') . '.sqlite.gz';
        $copy = $folder . '/' . $name . '.copy';
        Folder::removeFile($copy);

        $this->copyLiveDatabase($copy);
        try {
            $this->summary($copy);
            $this->compress($copy, $folder . '/' . $name);
        } finally {
            Folder::removeFile($copy);
        }
        $this->keepNewest();

        return $folder . '/' . $name;
    }

    /**
     * Restores a backup into a temporary copy, checks it, and says what is in it, like
     * "2 people, 31 days of visits". The copy is removed afterwards.
     *
     * @throws BackupFailed
     */
    public function check(string $backup): string
    {
        $copy = $this->temporaryFile();
        try {
            $this->decompress($backup, $copy);

            return $this->summary($copy);
        } finally {
            Folder::removeFile($copy);
        }
    }

    /**
     * Puts a backup back as the live database, after checking it. The database as it
     * was is kept first, and its path returned with what was restored.
     *
     * @return array{summary: string, before: string}
     *
     * @throws BackupFailed
     */
    public function restore(string $backup, DateTimeImmutable $now): array
    {
        $summary = $this->check($backup);
        $before = $this->folder() . '/before-restore-' . $now->setTimezone($this->time->zone)->format('Y-m-d-His') . '.sqlite';
        $this->copyLiveDatabase($before);

        $live = $this->paths->storage(Database::FILE);
        $incoming = $live . '.restoring';
        $this->decompress($backup, $incoming);
        if (! @rename($incoming, $live)) {
            Folder::removeFile($incoming);
            throw new BackupFailed("Could not replace {$live}. Nothing was changed.");
        }
        Folder::removeFile($live . '-journal');

        return ['summary' => $summary, 'before' => $before];
    }

    /** The newest backup on this server, or null when there is none. */
    public function newestHere(): ?string
    {
        return $this->here()[0] ?? null;
    }

    /** The path of a backup on this server, by name, or null when it is not here. */
    public function pathHere(string $name): ?string
    {
        $path = $this->folder() . '/' . $name;

        return preg_match(self::NAME, $name) === 1 && is_file($path) ? $path : null;
    }

    /** @return list<string> the backups on this server, newest first */
    public function here(): array
    {
        $names = array_values(array_filter(
            scandir($this->folder()) ?: [],
            static fn(string $name): bool => preg_match(self::NAME, $name) === 1,
        ));
        rsort($names);

        return array_map(fn(string $name): string => $this->folder() . '/' . $name, $names);
    }

    private function keepNewest(): void
    {
        foreach (array_slice($this->here(), self::KEPT_HERE) as $old) {
            Folder::removeFile($old);
        }
    }

    private function copyLiveDatabase(string $to): void
    {
        try {
            $live = Database::open($this->paths->storage(Database::FILE));
            $live->exec('VACUUM INTO ' . $live->quote($to));
        } catch (PDOException $problem) {
            Folder::removeFile($to);
            throw new BackupFailed('SQLite could not copy the database: ' . $problem->getMessage());
        } finally {
            $live = null;
        }
    }

    /** Checks a database file the way a restore needs it, and says what is in it. */
    private function summary(string $file): string
    {
        try {
            $pdo = Database::open($file);
            $verdict = $this->scalar($pdo, 'PRAGMA integrity_check');
            if ($verdict !== 'ok') {
                throw new BackupFailed('The backup is damaged. SQLite says: ' . $verdict);
            }
            $people = Database::hasTable($pdo, 'users') ? (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM users') : 0;
            $days = Database::hasTable($pdo, 'visit_days') ? (int) $this->scalar($pdo, 'SELECT COUNT(DISTINCT day) FROM visit_days') : 0;
        } catch (PDOException $problem) {
            throw new BackupFailed('The backup does not open as a database: ' . $problem->getMessage());
        } finally {
            $pdo = null;
        }

        return $people . ($people === 1 ? ' person' : ' people') . ', ' . $days . ($days === 1 ? ' day' : ' days') . ' of visits';
    }

    private function scalar(PDO $pdo, string $sql): string
    {
        $statement = $pdo->query($sql);
        $value = $statement === false ? false : $statement->fetchColumn();

        return is_scalar($value) ? (string) $value : '';
    }

    private function compress(string $from, string $to): void
    {
        $in = @fopen($from, 'rb');
        $out = @gzopen($to . '.part', 'wb9');
        if ($in === false || $out === false) {
            throw new BackupFailed("Could not write {$to}. Is the storage folder writable?");
        }
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        if (! @rename($to . '.part', $to)) {
            Folder::removeFile($to . '.part');
            throw new BackupFailed("Could not write {$to}. Is the storage folder writable?");
        }
    }

    private function decompress(string $from, string $to): void
    {
        $in = @gzopen($from, 'rb');
        $out = @fopen($to, 'wb');
        if ($in === false || $out === false) {
            throw new BackupFailed('Could not open ' . basename($from) . ' to restore it.');
        }
        while (! gzeof($in)) {
            $chunk = gzread($in, 1 << 20);
            if ($chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }
        gzclose($in);
        fclose($out);
    }

    private function folder(): string
    {
        $folder = $this->paths->storage(self::FOLDER);
        if (! is_dir($folder) && ! @mkdir($folder, 0700, true) && ! is_dir($folder)) {
            throw new BackupFailed("Could not make {$folder}. Is the storage folder writable?");
        }

        return $folder;
    }

    private function temporaryFile(): string
    {
        $folder = $this->paths->storage('tmp');
        if (! is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }

        return $folder . '/restore-check-' . bin2hex(random_bytes(6)) . '.sqlite';
    }
}
