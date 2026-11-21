<?php

declare(strict_types=1);

namespace SlimKiller\Cron\Jobs;

use DateTimeImmutable;
use SlimKiller\Cron\CronJob;
use SlimKiller\Cron\JobFailed;
use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Publishing\Fetcher;
use SlimKiller\Publishing\Releases;

/**
 * The safety net for a missed webhook call: every five minutes, fetch the newest build
 * if the website does not have it yet. Before publishing is set up there is nothing to
 * fetch, and that is not a failure.
 */
final readonly class DeployFetchJob implements CronJob
{
    public function __construct(
        private Fetcher $fetcher,
        private GitHubSettings $settings,
        private Releases $releases,
    ) {}

    public function name(): string
    {
        return 'deploy-fetch';
    }

    public function everyMinutes(): int
    {
        return 5;
    }

    public function run(DateTimeImmutable $now): string
    {
        if (! $this->settings->isComplete() || $this->releases->webRoot() === '') {
            return 'Publishing is not set up yet, so there is nothing to fetch.';
        }

        $result = $this->fetcher->fetch();
        if ($result->isFailed()) {
            throw new JobFailed($result->message);
        }

        return $result->message;
    }
}
