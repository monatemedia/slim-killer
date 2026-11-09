<?php

declare(strict_types=1);

use SlimKiller\Auth\AuditLog;
use SlimKiller\Auth\WelcomeLinks;
use SlimKiller\Auth\Role;
use SlimKiller\Auth\UserRepository;
use SlimKiller\Console\Command;
use SlimKiller\Console\Commands\InstallCommand;
use SlimKiller\Console\Commands\UserCreateCommand;
use SlimKiller\Console\Commands\UserResetCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Database\Database;
use SlimKiller\Database\Migrator;
use SlimKiller\Support\AppConfig;
use SlimKiller\Support\EnvFile;
use Tests\Fakes\SteppingClock;

/** @return array{0: int, 1: string} */
function runCommand(Command $command, array $argv): array
{
    $stream = fopen('php://memory', 'w+');
    $code = $command->run(Input::fromArgv(['hammer', $command->name(), ...$argv]), new Output($stream, false));
    rewind($stream);

    return [$code, (string) stream_get_contents($stream)];
}

function installCommand(PDO $pdo, string $envPath): InstallCommand
{
    $clock = new SteppingClock('2026-11-09 12:00');

    return new InstallCommand(
        new EnvFile($envPath),
        new Migrator($pdo, dirname(__DIR__, 3) . '/database/migrations'),
        new UserRepository($pdo, $clock),
        new WelcomeLinks($pdo, $clock),
        new AuditLog($pdo, $clock),
    );
}

it('installs: writes .env, creates the database and the first user, and prints a welcome link', function (): void {
    $pdo = Database::open(':memory:');
    $env = temporaryFolder() . '/.env';

    [$code, $output] = runCommand(installCommand($pdo, $env), ['--url=https://client.example/', '--email=Edward@Example.com', '--name=Edward Baitsewe', '--site-name=Honey Badger Namibia']);
    $user = (new UserRepository($pdo, new SteppingClock('2026-11-09')))->byEmail('edward@example.com');

    expect($code)->toBe(0)
        ->and((string) file_get_contents($env))->toContain('APP_URL="https://client.example"')
        ->and((string) file_get_contents($env))->toContain('APP_NAME="Honey Badger Namibia"')
        ->and($user?->role)->toBe(Role::Admin)
        ->and($user?->name)->toBe('Edward Baitsewe')
        ->and($output)->toMatch('#https://client\.example/cp/welcome\?token=[0-9a-f]{40}#');
});

it('does not install twice, and leaves a hand-edited .env alone', function (): void {
    $pdo = Database::open(':memory:');
    $env = temporaryFolder() . '/.env';
    file_put_contents($env, "APP_URL=\"https://kept.example\"\n");

    runCommand(installCommand($pdo, $env), ['--url=https://client.example', '--email=a@example.com', '--name=A']);
    [$code, $output] = runCommand(installCommand($pdo, $env), ['--url=https://client.example', '--email=b@example.com', '--name=B']);

    expect($code)->toBe(1)
        ->and($output)->toContain('already installed')
        ->and((string) file_get_contents($env))->toBe("APP_URL=\"https://kept.example\"\n");
});

it('explains what install needs when something is missing or wrong', function (array $argv): void {
    [$code, $output] = runCommand(installCommand(Database::open(':memory:'), temporaryFolder() . '/.env'), $argv);

    expect($code)->toBe(2)->and($output)->not->toContain('Installed');
})->with([
    'nothing' => [[]],
    'no name' => [['--url=https://a.example', '--email=a@example.com']],
    'bad address' => [['--url=not a website', '--email=a@example.com', '--name=A']],
    'bad email' => [['--url=https://a.example', '--email=nope', '--name=A']],
]);

it('adds a site owner and prints their welcome link', function (): void {
    $pdo = migratedDatabase();
    $clock = new SteppingClock('2026-11-09');
    $command = new UserCreateCommand(new UserRepository($pdo, $clock), new WelcomeLinks($pdo, $clock), new AuditLog($pdo, $clock), new AppConfig('https://client.example'));

    [$code, $output] = runCommand($command, ['--email=client@example.com', '--name=Client Name']);
    [$again] = runCommand($command, ['--email=client@example.com', '--name=Client Name']);

    expect($code)->toBe(0)
        ->and($output)->toContain('as site owner')
        ->and($output)->toContain('https://client.example/cp/welcome?token=')
        ->and($again)->toBe(1);
});

it('resets someone: no password, no two-factor, signed out, and a new welcome link', function (): void {
    $pdo = migratedDatabase();
    $clock = new SteppingClock('2026-11-09');
    $users = new UserRepository($pdo, $clock);
    $user = $users->create(SlimKiller\Auth\EmailAddress::from('client@example.com'), 'Client', Role::Owner);
    $users->setPassword($user->id, 'hash');
    $users->enableTwoFactor($user->id, 'SECRET', 1);
    $pdo->prepare('INSERT INTO sessions (id, user_id, data, created_at, expires_at) VALUES (?, ?, ?, ?, ?)')->execute(['s', $user->id, '{}', 0, PHP_INT_MAX]);

    [$code, $output] = runCommand(
        new UserResetCommand($users, new WelcomeLinks($pdo, $clock), new AuditLog($pdo, $clock), new AppConfig('https://client.example')),
        ['--email=client@example.com'],
    );
    $after = $users->byId($user->id);

    expect($code)->toBe(0)
        ->and($after?->hasPassword())->toBeFalse()
        ->and($after?->hasTwoFactor())->toBeFalse()
        ->and((int) $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn())->toBe(0)
        ->and($output)->toContain('/cp/welcome?token=');
});
