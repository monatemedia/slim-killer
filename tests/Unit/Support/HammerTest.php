<?php

declare(strict_types=1);

use SlimKiller\Cron\Heartbeat;
use SlimKiller\Support\Hammer;
use SlimKiller\Support\Paths;

afterEach(function (): void {
    Hammer::usePhp('php');
});

it('names a hammer command with "php" until told which PHP', function (): void {
    expect(Hammer::command('backup:connect'))->toBe('php hammer backup:connect')
        ->and(Hammer::command(''))->toBe('php hammer');
});

it('names it with the PHP hammer runs on', function (): void {
    Hammer::usePhp('/opt/alt/php84/usr/bin/php');

    expect(Hammer::command('doctor'))->toBe('/opt/alt/php84/usr/bin/php hammer doctor');
});

it('names it with the PHP cron runs on, for the panel and the website', function (): void {
    $paths = new Paths(temporaryFolder());
    (new Heartbeat($paths))->record(new DateTimeImmutable(), 'cron:run');

    Hammer::usePhpFromHeartbeat((new Heartbeat($paths))->file());

    expect(Hammer::command('setup:link'))->toBe(PHP_BINARY . ' hammer setup:link');
});

it('keeps "php" when cron has not run yet', function (): void {
    Hammer::usePhpFromHeartbeat(temporaryFolder() . '/no-heartbeat.json');

    expect(Hammer::command('doctor'))->toBe('php hammer doctor');
});
