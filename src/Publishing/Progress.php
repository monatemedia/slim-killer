<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

use SlimKiller\GitHub\GitHubSettings;
use SlimKiller\Http\HttpClient;
use SlimKiller\Support\Clock;

/**
 * How the newest save is getting on: Saved → Building → Going live → Live, or
 * "Build failed". Every stage comes from a fact:
 *
 * - Building: GitHub's record of the build for that commit (the Actions run).
 * - Going live: the build succeeded, and the host has not switched to it yet.
 * - Live: the live release was built from that commit.
 *
 * GitHub's answer is kept for a few seconds, so many open pages ask once. The token
 * needs "Actions: read"; without it the panel shows Building until the save is live.
 */
final class Progress
{
    /** How long a finished publish stays on screen. */
    public const int SHOW_DONE_SECONDS = 600;

    private const string API = 'https://api.github.com';

    private const int CACHE_SECONDS = 4;

    /** An estimate needs this many earlier publishes to go on. */
    private const int ESTIMATE_AFTER = 3;

    public function __construct(
        private readonly PublishLog $log,
        private readonly Releases $releases,
        private readonly HttpClient $http,
        private readonly GitHubSettings $settings,
        private readonly Clock $clock,
        private readonly string $cacheFile,
    ) {}

    /**
     * @return array{stage: string, message: string, estimate: string|null, done: bool}
     */
    public function report(): array
    {
        $save = $this->log->latest();
        $now = $this->clock->now();
        if ($save === null) {
            return $this->stage('none', '', done: true);
        }

        $finishedAt = $save['liveAt'] ?? $save['failedAt'];
        if ($finishedAt !== null && $now->getTimestamp() - $finishedAt > self::SHOW_DONE_SECONDS) {
            return $this->stage('none', '', done: true);
        }

        if ($save['liveAt'] !== null || $this->releases->live()?->commit === $save['commit']) {
            $this->log->markLive($save['commit'], $now);

            return $this->stage('live', 'Your change is live.', done: true);
        }
        if ($save['failedAt'] !== null) {
            return $this->failed();
        }

        $run = $this->run($save['commit']);
        if ($run === 'failed') {
            $this->log->markFailed($save['commit'], $now);

            return $this->failed();
        }

        $estimate = $this->estimate($now->getTimestamp() - $save['savedAt']);
        if ($run === 'built') {
            return $this->stage('going-live', 'Putting the new version on your website.', $estimate);
        }

        return $this->stage('building', $run === 'waiting'
            ? 'Saved. Waiting for GitHub to start building your website.'
            : 'Saved. GitHub is building your website.', $estimate);
    }

    /** "waiting", "building", "built" or "failed": GitHub's build for the commit. */
    private function run(string $commit): string
    {
        $cached = is_file($this->cacheFile) ? json_decode((string) file_get_contents($this->cacheFile), true) : null;
        if (is_array($cached) && ($cached['commit'] ?? null) === $commit
            && is_int($cached['at'] ?? null) && $this->clock->now()->getTimestamp() - $cached['at'] < self::CACHE_SECONDS
            && is_string($cached['run'] ?? null)) {
            return $cached['run'];
        }

        $response = $this->http->get(
            self::API . '/repos/' . $this->settings->repository . '/actions/runs?per_page=1&head_sha=' . rawurlencode($commit),
            [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $this->settings->token,
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
            10,
        );
        $runs = $response->ok() ? ($response->json()['workflow_runs'] ?? null) : null;
        $latest = is_array($runs) && is_array($runs[0] ?? null) ? $runs[0] : null;

        $run = match (true) {
            $latest === null => 'waiting',
            ($latest['status'] ?? null) !== 'completed' => ($latest['status'] ?? null) === 'in_progress' ? 'building' : 'waiting',
            ($latest['conclusion'] ?? null) === 'success' => 'built',
            default => 'failed',
        };

        if ($response->ok()) {
            if (! is_dir(dirname($this->cacheFile))) {
                @mkdir(dirname($this->cacheFile), 0755, true);
            }
            @file_put_contents($this->cacheFile, json_encode(['commit' => $commit, 'at' => $this->clock->now()->getTimestamp(), 'run' => $run]));
        }

        return $run;
    }

    /**
     * "About 2 minutes left", from the middle of this site's recent publishes, rounded to
     * the minute. None until there are a few to go on.
     */
    private function estimate(int $elapsed): ?string
    {
        $durations = array_slice($this->log->durations(), 0, 5);
        if (count($durations) < self::ESTIMATE_AFTER) {
            return null;
        }
        sort($durations);
        $typical = $durations[intdiv(count($durations), 2)];
        $left = $typical - $elapsed;
        if ($left <= 0) {
            return 'Any moment now.';
        }
        if ($left < 60) {
            return 'Under a minute left.';
        }
        $minutes = max(1, (int) round($left / 60));

        return 'About ' . $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes') . ' left.';
    }

    /** @return array{stage: string, message: string, estimate: string|null, done: bool} */
    private function failed(): array
    {
        return $this->stage('failed', 'The build failed, so your website is unchanged. Your change is saved. Monate Media can see what went wrong on GitHub.', done: true);
    }

    /** @return array{stage: string, message: string, estimate: string|null, done: bool} */
    private function stage(string $stage, string $message, ?string $estimate = null, bool $done = false): array
    {
        return ['stage' => $stage, 'message' => $message, 'estimate' => $estimate, 'done' => $done];
    }
}
