<?php

declare(strict_types=1);

namespace SlimKiller\Updates;

use SlimKiller\Http\HttpClient;
use SlimKiller\Support\Clock;
use SlimKiller\Support\Paths;

/**
 * Is there a newer Slim Killer? The version running is the newest release in this
 * copy's CHANGELOG.md (a server runs a checked-out tag, so it matches); the newest
 * there is comes from the release tags on GitHub, asked once a day by cron and again
 * by `hammer doctor`, and noted in storage so the dashboard never waits on GitHub.
 */
final readonly class Updates
{
    public const string REPOSITORY = 'monatemedia/slim-killer';

    public const string CHANGELOG_URL = 'https://github.com/' . self::REPOSITORY . '/blob/main/CHANGELOG.md';

    public const string FILE = 'update-check.json';

    private const string VERSION = '\d+\.\d+\.\d+';

    public function __construct(
        private HttpClient $http,
        private Paths $paths,
        private Clock $clock,
    ) {}

    /** The version this copy runs, like "0.8.1"; null when its CHANGELOG.md names no release. */
    public function installed(): ?string
    {
        $file = $this->paths->root('CHANGELOG.md');
        $text = is_file($file) ? (string) file_get_contents($file) : '';

        return preg_match('/^## \[(' . self::VERSION . ')\]/m', $text, $match) === 1 ? $match[1] : null;
    }

    /**
     * Asks GitHub for the newest release and notes it.
     *
     * @throws UpdateCheckFailed when GitHub can't be asked, or names no release
     */
    public function check(): string
    {
        $response = $this->http->get('https://api.github.com/repos/' . self::REPOSITORY . '/tags?per_page=100', ['Accept' => 'application/vnd.github+json'], 15);
        if (! $response->ok()) {
            throw new UpdateCheckFailed($response->status === 0
                ? 'no answer from api.github.com' . ($response->error !== '' ? ": {$response->error}" : '')
                : "api.github.com answered HTTP {$response->status}");
        }

        $latest = self::newest($response->body);
        if ($latest === null) {
            throw new UpdateCheckFailed('GitHub lists no release tags for ' . self::REPOSITORY);
        }
        $file = $this->paths->storage(self::FILE);
        if (is_dir(dirname($file)) || mkdir(dirname($file), 0755, true)) {
            file_put_contents($file, json_encode([
                'checked_at' => $this->clock->now()->format(DATE_ATOM),
                'latest' => $latest,
            ], JSON_THROW_ON_ERROR), LOCK_EX);
        }

        return $latest;
    }

    /** The newest release noted at the last check; null before the first. */
    public function latest(): ?string
    {
        $file = $this->paths->storage(self::FILE);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $latest = is_array($data) ? ($data['latest'] ?? null) : null;

        return is_string($latest) && preg_match('/^' . self::VERSION . '$/', $latest) === 1 ? $latest : null;
    }

    /** The newer release, when the last check found one; null when this copy is the newest. */
    public function newer(): ?UpdateNotice
    {
        return UpdateNotice::between($this->installed(), $this->latest());
    }

    /** The highest vX.Y.Z (or X.Y.Z) among GitHub's tags; others, like v1.0.0-beta, are skipped. */
    private static function newest(string $json): ?string
    {
        $tags = json_decode($json, true);
        $newest = null;
        foreach (is_array($tags) ? $tags : [] as $tag) {
            $name = is_array($tag) ? ($tag['name'] ?? null) : null;
            if (is_string($name) && preg_match('/^v?(' . self::VERSION . ')$/', $name, $match) === 1
                && ($newest === null || version_compare($match[1], $newest, '>'))) {
                $newest = $match[1];
            }
        }

        return $newest;
    }
}
