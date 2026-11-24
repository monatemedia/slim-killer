<?php

declare(strict_types=1);

use SlimKiller\Console\Commands\CronRunCommand;
use SlimKiller\Console\Input;
use SlimKiller\Console\Output;
use SlimKiller\Cron\CronRunner;
use SlimKiller\Cron\JobFailed;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Lock;
use SlimKiller\Support\Paths;
use Tests\Fakes\FakeJob;
use Tests\Fakes\SteppingClock;

/** A cron runner whose alerts go nowhere a test can't see. */
function cronRunner(array $jobs, Paths $paths, Clock $clock): CronRunner
{
    return new CronRunner($jobs, $paths, $clock, testAlerts($paths)[0]);
}

/** @return array{0: int, 1: string} the exit code and what was printed */
function runCron(CronRunner $runner, array $options = []): array
{
    $stream = fopen('php://memory', 'w+');
    $code = (new CronRunCommand($runner))->run(Input::fromArgv(['hammer', 'cron:run', ...$options]), new Output($stream, false));
    rewind($stream);

    return [$code, (string) stream_get_contents($stream)];
}

/** @param list<SlimKiller\Cron\JobReport> $reports */
function outcomes(array $reports): array
{
    $outcomes = [];
    foreach ($reports as $report) {
        $outcomes[$report->job] = $report->outcome->value;
    }

    return $outcomes;
}

it('runs every job on the first run, in order, and notes when each started', function (): void {
    $paths = new Paths(temporaryFolder());
    $order = [];
    $quick = new FakeJob('quick', work: function () use (&$order): string {
        $order[] = 'quick';

        return 'Quick.';
    });
    $slow = new FakeJob('slow', 5, function () use (&$order): string {
        $order[] = 'slow';

        return 'Slow.';
    });

    $reports = (cronRunner([$quick, $slow], $paths, new SteppingClock('2026-11-21T12:00:01+00:00')))->run();

    $record = json_decode((string) file_get_contents($paths->storage('cron/slow.json')), true);
    expect(outcomes($reports))->toBe(['quick' => 'done', 'slow' => 'done'])
        ->and($order)->toBe(['quick', 'slow'])
        ->and($record['started'])->toBe('2026-11-21T12:00:01+00:00')
        ->and($record['outcome'])->toBe('done')
        ->and($record['message'])->toBe('Slow.');
});

it('runs a job again only once its interval has passed, give or take cron being a few seconds late', function (): void {
    $paths = new Paths(temporaryFolder());
    $clock = new SteppingClock('2026-11-21T12:00:03+00:00');
    $everyMinute = new FakeJob('heartbeat');
    $everyFive = new FakeJob('fetch', 5);
    $runner = cronRunner([$everyMinute, $everyFive], $paths, $clock);

    $runner->run();
    $clock->advance(58);
    $second = $runner->run();
    $clock->advance(222);  // 4 minutes 40 seconds after the first run
    $third = $runner->run();

    expect(outcomes($second))->toBe(['heartbeat' => 'done', 'fetch' => 'not due'])
        ->and($second[1]->message)->toBe('Not due yet (every 5 minutes).')
        ->and(outcomes($third))->toBe(['heartbeat' => 'done', 'fetch' => 'done'])
        ->and($everyMinute->runs)->toBe(3)
        ->and($everyFive->runs)->toBe(2);
});

it('reports a job that fails, and still runs the jobs after it', function (): void {
    $paths = new Paths(temporaryFolder());
    $failing = new FakeJob('failing', work: fn(): string => throw new JobFailed('GitHub did not answer.'));
    $broken = new FakeJob('broken', work: fn(): string => throw new TypeError('A bug.'));
    $after = new FakeJob('after');

    $reports = (cronRunner([$failing, $broken, $after], $paths, new SteppingClock('2026-11-21T12:00:00+00:00')))->run();

    expect(outcomes($reports))->toBe(['failing' => 'failed', 'broken' => 'failed', 'after' => 'done'])
        ->and($reports[0]->message)->toBe('GitHub did not answer.')
        ->and($reports[1]->message)->toBe('TypeError: A bug.')
        ->and($after->runs)->toBe(1);
});

it('skips a job an earlier minute is still running, and runs the others', function (): void {
    $paths = new Paths(temporaryFolder());
    $slow = new FakeJob('slow');
    $quick = new FakeJob('quick');
    $earlier = Lock::attempt($paths->storage('cron/slow.lock'));

    $reports = (cronRunner([$slow, $quick], $paths, new SteppingClock('2026-11-21T12:00:00+00:00')))->run();
    $earlier?->release();

    expect(outcomes($reports))->toBe(['slow' => 'still running', 'quick' => 'done'])
        ->and($slow->runs)->toBe(0);
});

it('waits its interval after a run that crashed halfway, rather than crashing every minute', function (): void {
    $paths = new Paths(temporaryFolder());
    @mkdir($paths->storage('cron'), 0755, true);
    // What a crash leaves: the start is noted, the end never is.
    file_put_contents($paths->storage('cron/fetch.json'), '{"started":"2026-11-21T12:00:00+00:00"}');
    $fetch = new FakeJob('fetch', 5);

    $reports = (cronRunner([$fetch], $paths, new SteppingClock('2026-11-21T12:01:00+00:00')))->run();

    expect(outcomes($reports))->toBe(['fetch' => 'not due']);
});

it('prints nothing when all is well, so the host\'s cron log only shows problems', function (): void {
    $runner = cronRunner([new FakeJob('heartbeat')], new Paths(temporaryFolder()), new SteppingClock('2026-11-21T12:00:00+00:00'));

    expect(runCron($runner))->toBe([0, '']);
});

it('prints each failure and ends with exit code 1', function (): void {
    $runner = cronRunner([
        new FakeJob('deploy-fetch', work: fn(): string => throw new JobFailed('GitHub did not answer.')),
        new FakeJob('heartbeat'),
    ], new Paths(temporaryFolder()), new SteppingClock('2026-11-21T12:00:00+00:00'));

    // Output writes PHP_EOL: "\r\n" on Windows, "\n" on the host.
    expect(runCron($runner))->toBe([1, 'deploy-fetch: GitHub did not answer.' . PHP_EOL]);
});

it('lists every job with -v', function (): void {
    $runner = cronRunner([new FakeJob('heartbeat'), new FakeJob('contact-form')], new Paths(temporaryFolder()), new SteppingClock('2026-11-21T12:00:00+00:00'));

    [$code, $output] = runCron($runner, ['-v']);

    expect($code)->toBe(0)
        ->and($output)->toContain('heartbeat     Did heartbeat.')
        ->and($output)->toContain('contact-form  Did contact-form.');
});
