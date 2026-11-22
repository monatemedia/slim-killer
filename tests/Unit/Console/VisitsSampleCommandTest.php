<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\VisitsSampleCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Support\AppConfig;
use SlimKiller\Visits\SiteTime;
use SlimKiller\Visits\VisitLog;
use Tests\Fakes\FrozenClock;

/** @return array{int, string, PDO} exit code, what it printed, the database */
function sampleVisits(string $url, array $argv = [], ?PDO $pdo = null): array
{
    $pdo ??= migratedDatabase();
    $command = new VisitsSampleCommand($pdo, new VisitLog($pdo), SiteTime::named('UTC'), FrozenClock::at('2026-11-22T12:00:00Z'), new AppConfig($url));
    $stream = fopen('php://memory', 'w+');
    $code = $command->run(Input::fromArgv(['hammer', 'visits:sample', ...$argv]), new Output($stream, false));
    rewind($stream);

    return [$code, (string) stream_get_contents($stream), $pdo];
}

it('never makes up visits on a real website', function (string $url): void {
    [$code, $printed, $pdo] = sampleVisits($url);

    expect($code)->toBe(1)
        ->and($printed)->toContain('only go into a local copy')
        ->and($pdo->query('SELECT COUNT(*) FROM visit_events')->fetchColumn())->toBe(0);
})->with(['https://www.example.com', '']);

it('fills a local copy, adds up the finished days, and asks before doing it again', function (): void {
    [$code, $printed, $pdo] = sampleVisits('https://slim-killer.test', ['--days=10']);

    expect($code)->toBe(0)
        ->and($printed)->toContain('over 11 days, and added up 10 days')
        ->and((new VisitLog($pdo))->summary('2026-11-12', '2026-11-21')['visitors'])->toBeGreaterThan(0)
        ->and(sampleVisits('https://slim-killer.test', ['--days=10'], $pdo)[1])->toContain('Add --fresh')
        ->and(sampleVisits('https://slim-killer.test', ['--days=10', '--fresh'], $pdo)[0])->toBe(0);
});
