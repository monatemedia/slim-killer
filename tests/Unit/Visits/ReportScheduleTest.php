<?php

declare(strict_types=1);

use SlimKiller\Visits\ReportSchedule;

/** Thursday 26 November 2026, 13:30 in Johannesburg. */
function thursdayAfternoon(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-11-26 13:30', new DateTimeZone('Africa/Johannesburg'));
}

it('reads the four shapes, and says each in words', function (string $cron, string $words): void {
    $schedule = ReportSchedule::fromCron($cron);

    expect($schedule->describe())->toBe($words)
        ->and($schedule->cron())->toBe($words === 'Never' ? '' : $cron);
})->with([
    ['0 7 * * *', 'Daily at 07:00'],
    ['0 7 * * 1', 'Weekly, on Mondays at 07:00'],
    ['0 18 * * 7', 'Weekly, on Sundays at 18:00'],
    ['0 7 1 * *', 'Monthly, on the 1st at 07:00'],
    ['0 9 22 * *', 'Monthly, on the 22nd at 09:00'],
    ['0 7 L * *', 'Monthly, on the last day at 07:00'],
    ['', 'Never'],
    ['*/5 * * * *', 'Never'],
    ['0 7 31 * *', 'Never'],
]);

it('knows when it was last due and when it is next', function (string $cron, string $last, string $next): void {
    $schedule = ReportSchedule::fromCron($cron);

    expect($schedule->lastDue(thursdayAfternoon())?->format('Y-m-d H:i'))->toBe($last)
        ->and($schedule->nextDue(thursdayAfternoon())?->format('Y-m-d H:i'))->toBe($next);
})->with([
    'daily, already sent today' => ['0 7 * * *', '2026-11-26 07:00', '2026-11-27 07:00'],
    'daily, later today' => ['0 18 * * *', '2026-11-25 18:00', '2026-11-26 18:00'],
    'weekly on Mondays' => ['0 7 * * 1', '2026-11-23 07:00', '2026-11-30 07:00'],
    'weekly, today but later' => ['0 15 * * 4', '2026-11-19 15:00', '2026-11-26 15:00'],
    'monthly on the 1st' => ['0 7 1 * *', '2026-11-01 07:00', '2026-12-01 07:00'],
    'monthly on the last day' => ['0 7 L * *', '2026-10-31 07:00', '2026-11-30 07:00'],
]);

it('covers the time before each send, and compares it with the time before that', function (string $cron, array $expected): void {
    $schedule = ReportSchedule::fromCron($cron);
    $period = $schedule->period($schedule->lastDue(thursdayAfternoon()));

    expect([$period['first'], $period['last'], $period['before_first'], $period['before_last'], $period['before_count']])->toBe($expected);
})->with([
    'daily: yesterday, and the 7 days before' => ['0 7 * * *', ['2026-11-25', '2026-11-25', '2026-11-18', '2026-11-24', 7]],
    'weekly: last week, and the 4 weeks before' => ['0 7 * * 1', ['2026-11-16', '2026-11-22', '2026-10-19', '2026-11-15', 4]],
    'monthly on the 1st: last month, and the month before' => ['0 7 1 * *', ['2026-10-01', '2026-10-31', '2026-09-01', '2026-09-30', 1]],
]);

it('keeps choices from Preferences in range', function (): void {
    expect(ReportSchedule::fromChoices('weekly', 30, 9, 1)->cron())->toBe('0 23 * * 7')
        ->and(ReportSchedule::fromChoices('monthly', 7, 1, 31)->cron())->toBe('0 7 28 * *')
        ->and(ReportSchedule::fromChoices('hourly', 7, 1, 1)->isOff())->toBeTrue();
});
