<?php

declare(strict_types=1);

use SlimKiller\Backups\BackupEncryption;
use SlimKiller\Backups\BackupFailed;
use SlimKiller\Backups\Backups;
use SlimKiller\Backups\DatabaseBackup;
use SlimKiller\Backups\DriveStore;
use SlimKiller\Backups\GoogleDrive;
use SlimKiller\Console\Commands\BackupKeyCommand;
use SlimKiller\Console\Commands\BackupRestoreCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Cron\Jobs\BackupJob;
use SlimKiller\Database\Database;
use SlimKiller\Database\Migrator;
use SlimKiller\Doctor\Checks\BackupsCheck;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\EnvFile;
use SlimKiller\Support\Paths;
use SlimKiller\Visits\SiteTime;
use Tests\Fakes\FakeGoogle;
use Tests\Fakes\FrozenClock;

/** 02:15 on 24 November in Johannesburg. */
const BACKUP_NOW = '2026-11-24T00:15:00+00:00';

const BACKUP_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

/**
 * A site with one person and one day of visits in its database. Google Drive is set up
 * in .env; pass $connected to have it connected too.
 *
 * @return array{backups: Backups, drive: GoogleDrive, store: DriveStore, database: DatabaseBackup, paths: Paths, google: FakeGoogle, time: SiteTime, encryption: BackupEncryption}
 */
function backupSite(bool $connected = false, int $kept = 731, bool $setUp = true, string $key = BACKUP_KEY): array
{
    $paths = new Paths(temporaryFolder());
    $pdo = Database::open($paths->storage(Database::FILE));
    (new Migrator($pdo, dirname(__DIR__, 3) . '/database/migrations'))->migrate();
    $pdo->exec("INSERT INTO users (email, name, role, created_at) VALUES ('owner@example.com', 'Owner', 'owner', 0)");
    $pdo->exec("INSERT INTO visit_days (day, kind, path, label, source, medium, campaign, device, visitors, events) VALUES ('2026-11-22', 'page', '/', '', '', '', '', 'phone', 3, 5)");
    $pdo = null;

    $config = new AppConfig(
        timezone: 'Africa/Johannesburg',
        googleClientId: $setUp ? 'client-id' : '',
        googleClientSecret: $setUp ? 'client-secret' : '',
        backupsKept: $kept,
    );
    $time = SiteTime::named('Africa/Johannesburg');
    $google = new FakeGoogle();
    $store = new DriveStore($paths);
    if ($connected) {
        $store->connected('refresh-1');
    }
    $drive = new GoogleDrive($google->http, $config, $store);
    $database = new DatabaseBackup($paths, $time);
    $encryption = new BackupEncryption($key);

    return [
        'backups' => new Backups($database, $drive, $encryption, $config, $time, $paths),
        'encryption' => $encryption,
        'drive' => $drive,
        'store' => $store,
        'database' => $database,
        'paths' => $paths,
        'google' => $google,
        'time' => $time,
    ];
}

function minutesAfter(string $time, int $minutes): DateTimeImmutable
{
    return (new DateTimeImmutable($time))->modify("+{$minutes} minutes");
}

function peopleIn(Paths $paths): int
{
    $pdo = Database::open($paths->storage(Database::FILE));
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $pdo = null;

    return $count;
}

// --- On this server ----------------------------------------------------------------

it('makes a gzipped backup named in the site\'s time, which restores whole', function (): void {
    ['database' => $database] = backupSite();

    $path = $database->make(new DateTimeImmutable(BACKUP_NOW));

    expect(basename($path))->toBe('backup-2026-11-24-0215.sqlite.gz')
        ->and(substr((string) file_get_contents($path), 0, 2))->toBe("\x1f\x8b")
        ->and($database->check($path))->toBe('1 person, 1 day of visits');
});

it('keeps the newest 7 backups on this server', function (): void {
    ['database' => $database] = backupSite();

    foreach (range(0, 8) as $minute) {
        $database->make(minutesAfter(BACKUP_NOW, $minute));
    }

    expect(array_map(basename(...), $database->here()))->toHaveCount(7)
        ->and(basename($database->here()[0]))->toBe('backup-2026-11-24-0223.sqlite.gz')
        ->and(basename($database->here()[6]))->toBe('backup-2026-11-24-0217.sqlite.gz');
});

it('refuses a backup that is not a whole database', function (): void {
    ['database' => $database, 'paths' => $paths] = backupSite();
    $bad = $paths->storage('backups/backup-2026-11-24-0215.sqlite.gz');
    @mkdir(dirname($bad), 0755, true);
    file_put_contents($bad, gzencode(str_repeat('not a database ', 100)));

    expect(fn() => $database->check($bad))->toThrow(BackupFailed::class, 'does not open as a database');
});

it('makes one backup a day, on this server only while Google Drive is not connected', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite();

    $first = $backups->daily(new DateTimeImmutable(BACKUP_NOW));
    $again = $backups->daily(minutesAfter(BACKUP_NOW, 60));

    expect($first)->toBe('Saved backup-2026-11-24-0215.sqlite.gz on this server only: Google Drive is not connected (php hammer backup:connect).')
        ->and($again)->toBe('Already backed up today (backup-2026-11-24-0215.sqlite.gz).')
        ->and($google->http->requested)->toBe([]);
});

// --- Google Drive --------------------------------------------------------------------

it('sends the backup encrypted to a folder it makes in the root of Google Drive, checked against Google\'s checksum', function (): void {
    ['backups' => $backups, 'google' => $google, 'database' => $database, 'encryption' => $encryption] = backupSite(connected: true);

    $message = $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    $sent = array_values($google->files)[0]['bytes'];

    expect($message)->toBe('Saved backup-2026-11-24-0215.sqlite.gz here and, encrypted, on Google Drive.')
        ->and($google->folders)->toBe(['folder-1' => 'Website backups'])
        ->and($google->names())->toBe(['backup-2026-11-24-0215.sqlite.gz.enc'])
        ->and($sent)->toStartWith('SKB1')
        ->and($sent)->not->toContain('owner@example.com')
        ->and($encryption->unlock($sent))->toBe(file_get_contents((string) $database->newestHere()))
        ->and($backups->last())->toBe(['day' => '2026-11-24', 'file' => 'backup-2026-11-24-0215.sqlite.gz', 'sent' => true]);
});

it('finds the folder once, and asks Google for a token once', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite(connected: true);

    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    $backups->backUp(minutesAfter(BACKUP_NOW, 1));

    $tokens = array_filter($google->http->requested, static fn(string $request): bool => str_contains($request, GoogleDrive::TOKEN_URL));
    $folders = array_filter($google->http->requested, static fn(string $request): bool => str_contains($request, 'mimeType'));
    expect($tokens)->toHaveCount(1)
        ->and($folders)->toHaveCount(1);
});

it('keeps BACKUPS_KEPT backups on Google Drive, removing the oldest', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite(connected: true, kept: 3);

    foreach (range(0, 3) as $minute) {
        $message = $backups->backUp(minutesAfter(BACKUP_NOW, $minute));
    }

    expect($google->names())->toBe([
        'backup-2026-11-24-0216.sqlite.gz.enc',
        'backup-2026-11-24-0217.sqlite.gz.enc',
        'backup-2026-11-24-0218.sqlite.gz.enc',
    ])->and($message)->toBe('Saved backup-2026-11-24-0218.sqlite.gz here and, encrypted, on Google Drive, which keeps 3: removed the 1 oldest.');
});

it('sends a backup that did not reach Google Drive again the next hour, without making another', function (): void {
    ['backups' => $backups, 'google' => $google, 'database' => $database] = backupSite(connected: true);
    $google->failUploads = true;

    expect(fn() => $backups->daily(new DateTimeImmutable(BACKUP_NOW)))
        ->toThrow(BackupFailed::class, 'Saved backup-2026-11-24-0215.sqlite.gz on this server, but not on Google Drive: Google Drive did not take the upload (Backend Error).');

    $google->failUploads = false;
    $message = $backups->daily(minutesAfter(BACKUP_NOW, 60));

    expect($message)->toBe('Saved backup-2026-11-24-0215.sqlite.gz here and, encrypted, on Google Drive.')
        ->and($database->here())->toHaveCount(1)
        ->and($google->names())->toBe(['backup-2026-11-24-0215.sqlite.gz.enc']);
});

it('removes an upload whose checksum does not match, and fails', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite(connected: true);
    $google->damageUploads = true;

    expect(fn() => $backups->backUp(new DateTimeImmutable(BACKUP_NOW)))
        ->toThrow(BackupFailed::class, 'did not match this one, so it was removed')
        ->and($google->files)->toBe([])
        ->and($backups->last()['sent'] ?? null)->toBeFalse();
});

it('makes the folder again when it was deleted in Google Drive', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite(connected: true);
    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    $google->folders = [];
    $google->files = [];

    $backups->backUp(minutesAfter(BACKUP_NOW, 1));

    expect(array_values($google->folders))->toBe(['Website backups'])
        ->and($google->names())->toBe(['backup-2026-11-24-0216.sqlite.gz.enc']);
});

it('sends nothing to Google Drive without BACKUP_KEY', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite(connected: true, key: '');

    expect(fn() => $backups->backUp(new DateTimeImmutable(BACKUP_NOW)))
        ->toThrow(BackupFailed::class, 'Saved backup-2026-11-24-0215.sqlite.gz on this server, but not on Google Drive: BACKUP_KEY in .env is empty')
        ->and($google->files)->toBe([]);
});

it('says to connect again when Google no longer accepts the connection', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite(connected: true);
    $google->refuseRefresh = true;

    expect(fn() => $backups->backUp(new DateTimeImmutable(BACKUP_NOW)))
        ->toThrow(BackupFailed::class, 'Google no longer accepts this website\'s connection');
});

// --- Connecting ----------------------------------------------------------------------

it('connects with a link that asks only for the files it makes, then the address the browser landed on', function (): void {
    ['drive' => $drive, 'store' => $store, 'google' => $google] = backupSite();

    parse_str((string) parse_url($drive->connectLink(), PHP_URL_QUERY), $link);
    $drive->connect(GoogleDrive::REDIRECT . '?state=' . $link['state'] . '&code=4/abc&scope=' . urlencode(GoogleDrive::SCOPE));

    $exchange = $google->http->sent[0];
    parse_str((string) $exchange['body'], $form);
    expect($link)->toMatchArray([
        'client_id' => 'client-id',
        'scope' => GoogleDrive::SCOPE,
        'access_type' => 'offline',
        'code_challenge_method' => 'S256',
        'redirect_uri' => GoogleDrive::REDIRECT,
    ])
        ->and($link['code_challenge'])->toBe(rtrim(strtr(base64_encode(hash('sha256', $form['code_verifier'], true)), '+/', '-_'), '='))
        ->and($form['code'])->toBe('4/abc')
        ->and($store->refreshToken())->toBe('refresh-1')
        ->and($store->connecting())->toBeNull()
        ->and($drive->isConnected())->toBeTrue()
        ->and(array_values($google->folders))->toBe(['Website backups']);
});

it('refuses an address from another link', function (): void {
    ['drive' => $drive] = backupSite();
    $drive->connectLink();

    expect(fn() => $drive->connect(GoogleDrive::REDIRECT . '?state=old&code=4/abc'))
        ->toThrow(BackupFailed::class, 'That address is from another link');
});

it('says so when the link opened, or the placeholder, is pasted instead of the address landed on', function (): void {
    ['drive' => $drive, 'google' => $google] = backupSite();
    $link = $drive->connectLink();

    expect(fn() => $drive->connect($link))->toThrow(BackupFailed::class, 'That is the link you opened')
        ->and(fn() => $drive->connect('<the address>'))->toThrow(BackupFailed::class, 'in place of <the address>')
        ->and($google->http->requested)->toBe([]);
});

it('asks for the link first when there is none', function (): void {
    ['drive' => $drive] = backupSite();

    expect(fn() => $drive->connect('4/abc'))->toThrow(BackupFailed::class, 'without --code first');
});

it('keeps the refresh token out of the web root, readable by the site\'s user only', function (): void {
    ['store' => $store, 'paths' => $paths] = backupSite(connected: true);

    expect($paths->storage(DriveStore::FILE))->toBeFile()
        ->and(json_decode((string) file_get_contents($paths->storage(DriveStore::FILE)), true))->toBe(['refresh_token' => 'refresh-1']);
    if (DIRECTORY_SEPARATOR === '/') {
        expect(fileperms($paths->storage(DriveStore::FILE)) & 0777)->toBe(0600);
    }
});

// --- Test restore and restore ----------------------------------------------------------

it('test-restores the newest backup from Google Drive', function (): void {
    ['backups' => $backups, 'google' => $google] = backupSite(connected: true);
    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    $backups->backUp(minutesAfter(BACKUP_NOW, 1));

    expect($backups->checkNewest())->toBe('Restored backup-2026-11-24-0216.sqlite.gz.enc from Google Drive into a temporary copy: 1 person, 1 day of visits.')
        ->and(end($google->http->requested))->toStartWith('DOWNLOAD ' . GoogleDrive::FILES_URL);
});

it('test-restores the newest backup here when Google Drive is not connected', function (): void {
    ['backups' => $backups] = backupSite();
    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));

    expect($backups->checkNewest())->toStartWith('Restored backup-2026-11-24-0215.sqlite.gz from this server into a temporary copy: 1 person, 1 day of visits.');
});

it('puts a backup back as the database, keeping the database as it was', function (): void {
    ['backups' => $backups, 'paths' => $paths] = backupSite();
    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    $pdo = Database::open($paths->storage(Database::FILE));
    $pdo->exec("INSERT INTO users (email, name, role, created_at) VALUES ('admin@example.com', 'Admin', 'admin', 0)");
    $pdo = null;

    $restored = $backups->restore('backup-2026-11-24-0215.sqlite.gz', minutesAfter(BACKUP_NOW, 30));

    expect(peopleIn($paths))->toBe(1)
        ->and($restored['from'])->toBe('this server')
        ->and($restored['summary'])->toBe('1 person, 1 day of visits')
        ->and(basename($restored['before']))->toBe('before-restore-2026-11-24-024500.sqlite')
        ->and($restored['before'])->toBeFile();
});

it('restores from Google Drive when the backup is no longer on this server', function (): void {
    ['backups' => $backups, 'database' => $database, 'paths' => $paths] = backupSite(connected: true);
    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    unlink((string) $database->newestHere());

    $restored = $backups->restore('backup-2026-11-24-0215.sqlite.gz', minutesAfter(BACKUP_NOW, 30));

    expect($restored['from'])->toBe('Google Drive')
        ->and(peopleIn($paths))->toBe(1);
});

it('names a backup made with another key as that, when restoring', function (): void {
    ['backups' => $backups, 'google' => $google, 'database' => $database] = backupSite(connected: true);
    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    unlink((string) $database->newestHere());
    $id = array_key_first($google->files);
    $google->files[$id]['bytes'] = (new BackupEncryption(str_repeat('ab', 32)))->lock('another site');

    expect(fn() => $backups->restore('backup-2026-11-24-0215.sqlite.gz.enc', minutesAfter(BACKUP_NOW, 30)))
        ->toThrow(BackupFailed::class, 'encrypted with a different BACKUP_KEY');
});

it('restores only a backup, by its name', function (): void {
    ['backups' => $backups] = backupSite();

    expect(fn() => $backups->restore('../database.sqlite', new DateTimeImmutable(BACKUP_NOW)))
        ->toThrow(BackupFailed::class, 'is not a backup\'s name');
});

it('restores nothing without --yes', function (): void {
    ['backups' => $backups, 'paths' => $paths] = backupSite();
    $backups->backUp(new DateTimeImmutable(BACKUP_NOW));
    $stream = fopen('php://memory', 'w+');

    $code = (new BackupRestoreCommand($backups, FrozenClock::at(BACKUP_NOW)))
        ->run(new Input('backup:restore', ['backup-2026-11-24-0215.sqlite.gz']), new Output($stream, false));
    rewind($stream);

    expect($code)->toBe(1)
        ->and(stream_get_contents($stream))->toContain('backup:restore backup-2026-11-24-0215.sqlite.gz --yes')
        ->and(glob($paths->storage('backups/before-restore-*')))->toBe([]);
});

it('makes no backup, and the doctor says what is at stake, when BACKUPS is off', function (): void {
    ['backups' => $backups, 'drive' => $drive, 'encryption' => $encryption, 'time' => $time, 'database' => $database] = backupSite(connected: true);
    $off = AppConfig::fromEnvironment(['BACKUPS' => 'off']);

    $message = (new BackupJob($backups, $time, $off))->run(new DateTimeImmutable(BACKUP_NOW));
    [$result] = (new BackupsCheck($backups, $drive, $encryption, $time, FrozenClock::at(BACKUP_NOW), $off))->check();

    expect($message)->toBe('Backups are off (BACKUPS="off" in .env).')
        ->and($database->here())->toBe([])
        ->and($result->status->value)->toBe('info')
        ->and($result->detail)->toContain('Pages and photos are safe in GitHub')
        ->and(AppConfig::fromEnvironment([])->backupsOff)->toBeFalse();
});

// --- Encryption ------------------------------------------------------------------------

it('encrypts so that only the same key opens it, and any change shows', function (): void {
    $encryption = new BackupEncryption(BACKUP_KEY);
    $locked = $encryption->lock('the database');
    $changed = substr($locked, 0, -1) . chr(ord(substr($locked, -1)) ^ 1);

    expect($encryption->unlock($locked))->toBe('the database')
        ->and($encryption->lock('the database'))->not->toBe($locked)
        ->and(fn() => $encryption->unlock($changed))->toThrow(BackupFailed::class, 'is damaged')
        ->and(fn() => $encryption->unlock('backup-plain.gz'))->toThrow(BackupFailed::class, 'not an encrypted Slim Killer backup')
        ->and((new BackupEncryption('too short'))->isSetUp())->toBeFalse();
});

it('adds a new key to .env once, and never replaces it', function (): void {
    $env = temporaryFolder() . '/.env';
    file_put_contents($env, "APP_URL=\"https://example.com\"\n");
    $stream = fopen('php://memory', 'w+');

    $code = (new BackupKeyCommand(new EnvFile($env), new BackupEncryption('')))->run(new Input('backup:key'), new Output($stream, false));
    preg_match('/^BACKUP_KEY="([0-9a-f]{64})"$/m', (string) file_get_contents($env), $added);
    $again = (new BackupKeyCommand(new EnvFile($env), new BackupEncryption($added[1])))->run(new Input('backup:key'), new Output($stream, false));
    rewind($stream);
    $printed = (string) stream_get_contents($stream);

    expect($code)->toBe(0)
        ->and($again)->toBe(0)
        ->and($printed)->toContain($added[1])
        ->and($printed)->toContain('is never replaced')
        ->and(substr_count((string) file_get_contents($env), 'BACKUP_KEY='))->toBe(1);
});

// --- Cron and the doctor ------------------------------------------------------------------

it('makes the day\'s backup only after 02:00 in the site\'s time', function (): void {
    ['backups' => $backups, 'time' => $time] = backupSite();
    $job = new BackupJob($backups, $time, new AppConfig());

    expect($job->run(new DateTimeImmutable('2026-11-23T23:30:00+00:00')))->toBe('The day\'s backup is made after 02:00.')
        ->and($job->run(new DateTimeImmutable(BACKUP_NOW)))->toStartWith('Saved backup-2026-11-24-0215.sqlite.gz');
});

it('fails the cron job, saying why, when the backup does not reach Google Drive', function (): void {
    ['backups' => $backups, 'time' => $time, 'google' => $google] = backupSite(connected: true);
    $google->failUploads = true;

    expect(fn() => (new BackupJob($backups, $time, new AppConfig()))->run(new DateTimeImmutable(BACKUP_NOW)))
        ->toThrow(JobFailed::class, 'but not on Google Drive');
});

it('tells the doctor how backups stand', function (bool $setUp, bool $connected, ?string $at, string $status, string $title, string $key = BACKUP_KEY): void {
    ['backups' => $backups, 'drive' => $drive, 'time' => $time, 'encryption' => $encryption] = backupSite(connected: $connected, setUp: $setUp, key: $key);
    if ($at !== null) {
        try {
            $backups->backUp(new DateTimeImmutable($at));
        } catch (BackupFailed) {
            // Made here, not sent: what the doctor is asked about.
        }
    }

    [$result] = (new BackupsCheck($backups, $drive, $encryption, $time, FrozenClock::at('2026-11-24T10:00:00+00:00'), stagingConfig()))->check();

    expect($result->status->value)->toBe($status)
        ->and($result->title)->toBe($title);
})->with([
    'no backup yet' => [true, true, null, 'warn', 'No backup yet'],
    'on Google Drive too' => [true, true, BACKUP_NOW, 'pass', 'Backups'],
    'not set up' => [false, false, BACKUP_NOW, 'warn', 'Backups stay on this server'],
    'not connected' => [true, false, BACKUP_NOW, 'warn', 'Google Drive is not connected'],
    'stopped' => [true, true, '2026-11-20T01:00:00+00:00', 'fail', 'Backups have stopped'],
    'no key' => [true, true, BACKUP_NOW, 'fail', 'Backups can\'t go to Google Drive', ''],
]);
