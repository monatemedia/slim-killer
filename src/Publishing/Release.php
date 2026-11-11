<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

/**
 * One built site on the host, in its own folder under the web root's _site/.
 *
 * `build` is the commit on the dist branch (one per build); `commit` is the save it was
 * built from, on the client site's branch. The folder is named after the build.
 */
final readonly class Release
{
    public function __construct(
        public string $id,
        public string $build,
        public string $commit,
        public string $builtAt,
        public string $fetchedAt,
    ) {}

    /** @return array{id: string, build: string, commit: string, builtAt: string, fetchedAt: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'build' => $this->build,
            'commit' => $this->commit,
            'builtAt' => $this->builtAt,
            'fetchedAt' => $this->fetchedAt,
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $text = static fn(string $key): string => is_string($data[$key] ?? null) ? $data[$key] : '';
        if ($text('id') === '' || $text('build') === '') {
            return null;
        }

        return new self($text('id'), $text('build'), $text('commit'), $text('builtAt'), $text('fetchedAt'));
    }

    /** "d9bc3e2": the save, as GitHub shortens it. */
    public function shortCommit(): string
    {
        return substr($this->commit !== '' ? $this->commit : $this->build, 0, 7);
    }
}
