<?php

declare(strict_types=1);

use SlimKiller\Doctor\CheckResult;
use SlimKiller\Doctor\Report;
use SlimKiller\Doctor\ReportStore;
use SlimKiller\Doctor\Section;
use SlimKiller\Support\Paths;
use Tests\Fakes\FrozenClock;

it('gives back the report it saved, and when it was checked', function (): void {
    $store = new ReportStore(new Paths(temporaryFolder()), FrozenClock::at('2026-11-09T12:00:00+00:00'));
    $store->save(new Report([
        new Section('Server', [
            CheckResult::pass('Storage folder is writable', '/home/site/storage'),
            CheckResult::warn('Cron has not run yet', 'add this cron job', '/opt/alt/php84/usr/bin/php hammer cron:heartbeat'),
        ]),
    ]));

    $saved = $store->latest();
    $results = $saved?->report->sections[0]->results ?? [];

    expect($saved?->checkedAt->format(DATE_ATOM))->toBe('2026-11-09T12:00:00+00:00')
        ->and($saved?->report->sections[0]->title)->toBe('Server')
        ->and(statuses($results))->toBe(['pass', 'warn'])
        ->and($results[1]->hint)->toBe('/opt/alt/php84/usr/bin/php hammer cron:heartbeat');
});

it('has no report before the doctor has run', function (): void {
    expect((new ReportStore(new Paths(temporaryFolder()), FrozenClock::at('2026-11-09')))->latest())->toBeNull();
});

it('ignores a report it cannot read', function (string $contents): void {
    $paths = new Paths(temporaryFolder());
    mkdir($paths->storage(), 0755, true);
    file_put_contents($paths->storage(ReportStore::FILE), $contents);

    expect((new ReportStore($paths, FrozenClock::at('2026-11-09')))->latest())->toBeNull();
})->with([
    'not JSON' => ['{"checked_at":'],
    'no time' => ['{"sections":[]}'],
    'unknown status' => ['{"checked_at":"2026-11-09T12:00:00+00:00","sections":[{"title":"Server","results":[{"status":"maybe","title":"Cron"}]}]}'],
]);
