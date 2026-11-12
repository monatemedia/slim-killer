<?php

declare(strict_types=1);

namespace SlimKiller\Publishing;

/**
 * What a fetch or a rollback did: switched to a release, found nothing new, found
 * another fetch already running, or failed (and the website is unchanged).
 */
final readonly class FetchResult
{
    private function __construct(
        public string $outcome,
        public string $message,
        public ?Release $release = null,
    ) {}

    public static function switched(Release $release, string $message): self
    {
        return new self('switched', $message, $release);
    }

    public static function unchanged(?Release $live): self
    {
        return new self('unchanged', $live === null
            ? 'Nothing new to fetch.'
            : 'Nothing new to fetch. Live: the build of ' . $live->shortCommit() . '.', $live);
    }

    public static function busy(): self
    {
        return new self('busy', 'Another fetch is running. It will finish the job.');
    }

    public static function failed(string $message): self
    {
        return new self('failed', $message);
    }

    public function isFailed(): bool
    {
        return $this->outcome === 'failed';
    }
}
