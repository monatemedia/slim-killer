<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

/**
 * One built site on the host, in its own folder under the web root's _site/.
 *
 * `build` is the commit on the dist branch (one per build); `commit` is the save it was
 * built from, on the client site's branch. The folder is named after the build.
 * `redirects` are the build's old addresses and where they now go (site/slim-killer.json);
 * the host writes them as 301 rules while this release is live.
 */
final readonly class Release
{
    public function __construct(
        public string $id,
        public string $build,
        public string $commit,
        public string $builtAt,
        public string $fetchedAt,
        /** @var list<array{from: string, to: string}> */
        public array $redirects = [],
    ) {}

    /**
     * The redirects worth keeping from a build's redirects.json: an old path on this site,
     * and a path or a full http(s) address to send it to. Anything else is dropped, since
     * each one becomes a line in the website's .htaccess.
     *
     * @return list<array{from: string, to: string}>
     */
    public static function redirectsFrom(mixed $data): array
    {
        $redirects = [];
        foreach (is_array($data) ? $data : [] as $redirect) {
            $from = is_array($redirect) && is_string($redirect['from'] ?? null) ? $redirect['from'] : '';
            $to = is_array($redirect) && is_string($redirect['to'] ?? null) ? $redirect['to'] : '';
            if (preg_match('#^/[A-Za-z0-9._~/-]*[A-Za-z0-9._~-]/?$#', $from) === 1
                && preg_match('#^(https?://[A-Za-z0-9.-]+(:[0-9]+)?)?/[A-Za-z0-9._~/-]*$#', $to) === 1) {
                $redirects[] = ['from' => $from, 'to' => $to];
            }
        }

        return $redirects;
    }

    /** @return array{id: string, build: string, commit: string, builtAt: string, fetchedAt: string, redirects: list<array{from: string, to: string}>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'build' => $this->build,
            'commit' => $this->commit,
            'builtAt' => $this->builtAt,
            'fetchedAt' => $this->fetchedAt,
            'redirects' => $this->redirects,
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $text = static fn(string $key): string => is_string($data[$key] ?? null) ? $data[$key] : '';
        if ($text('id') === '' || $text('build') === '') {
            return null;
        }

        return new self($text('id'), $text('build'), $text('commit'), $text('builtAt'), $text('fetchedAt'), self::redirectsFrom($data['redirects'] ?? []));
    }

    /** "d9bc3e2": the save, as GitHub shortens it. */
    public function shortCommit(): string
    {
        return substr($this->commit !== '' ? $this->commit : $this->build, 0, 7);
    }
}
