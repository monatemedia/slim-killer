<?php

declare(strict_types=1);

namespace SlimKiller\Cron;

use DateTimeImmutable;
use RuntimeException;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Lock;
use SlimKiller\Support\Paths;
use Throwable;

/**
 * Runs each job whose time has come, one after another. Cron calls it every minute
 * through `hammer cron:run`, so a host needs one cron line.
 *
 * - Each job has its own lock. A slow job (a fetch) holds up only itself: the next
 *   minute's run skips it and still does the others.
 * - Each job runs inside its own try/catch. A job that fails is reported, and the jobs
 *   after it still run.
 * - When a job starts and how it ended are kept in storage/cron/<job>.json. The start is
 *   written first, so a job that crashes the process waits its interval before it is
 *   tried again, instead of crashing every minute.
 *
 * There is no time limit for each job: PHP cannot stop a job halfway without the
 * functions shared hosts disable (proc_open, pcntl). The network calls have their own
 * time limits, and the locks stop slow runs piling up.
 */
final class CronRunner
{
    /**
     * A job that started this many seconds short of its interval ago counts as due:
     * cron starts each minute a few seconds early or late.
     */
    private const int EARLY_SECONDS = 30;

    /** @param list<CronJob> $jobs in the order they run: quick ones first */
    public function __construct(
        private readonly array $jobs,
        private readonly Paths $paths,
        private readonly Clock $clock,
    ) {}

    /** @return list<JobReport> one for each job, in order */
    public function run(): array
    {
        $reports = [];
        foreach ($this->jobs as $job) {
            $reports[] = $this->runOne($job);
        }

        return $reports;
    }

    private function runOne(CronJob $job): JobReport
    {
        $name = $job->name();
        try {
            $lock = Lock::attempt($this->paths->storage("cron/{$name}.lock"));
        } catch (RuntimeException $problem) {
            return new JobReport($name, JobOutcome::Failed, $problem->getMessage());
        }
        if ($lock === null) {
            return new JobReport($name, JobOutcome::StillRunning, 'Still running from an earlier minute.');
        }

        try {
            $now = $this->clock->now();
            $last = $this->lastStart($name);
            if ($last !== null && $now->getTimestamp() - $last->getTimestamp() < $job->everyMinutes() * 60 - self::EARLY_SECONDS) {
                return new JobReport($name, JobOutcome::NotDue, 'Not due yet (every ' . $this->every($job) . ').');
            }

            $this->record($name, ['started' => $now->format(DATE_ATOM)]);
            try {
                $report = new JobReport($name, JobOutcome::Done, $job->run($now));
            } catch (JobFailed $failed) {
                $report = new JobReport($name, JobOutcome::Failed, $failed->getMessage());
            } catch (Throwable $fault) {
                // A fault in the code or on the host: name it plainly for whoever reads the log.
                $report = new JobReport($name, JobOutcome::Failed, $fault::class . ': ' . $fault->getMessage());
            }
            $this->record($name, [
                'started' => $now->format(DATE_ATOM),
                'finished' => $this->clock->now()->format(DATE_ATOM),
                'outcome' => $report->outcome->value,
                'message' => $report->message,
            ]);

            return $report;
        } finally {
            $lock->release();
        }
    }

    private function lastStart(string $name): ?DateTimeImmutable
    {
        $file = $this->recordFile($name);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data) || ! is_string($data['started'] ?? null)) {
            return null;
        }
        $started = DateTimeImmutable::createFromFormat(DATE_ATOM, $data['started']);

        return $started === false ? null : $started;
    }

    /** @param array<string, string> $data */
    private function record(string $name, array $data): void
    {
        @file_put_contents($this->recordFile($name), json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function recordFile(string $name): string
    {
        return $this->paths->storage("cron/{$name}.json");
    }

    private function every(CronJob $job): string
    {
        return $job->everyMinutes() === 1 ? 'minute' : $job->everyMinutes() . ' minutes';
    }
}
